<?php

namespace App\Services\Invoice\Extraction;

use App\Enums\ErrorCode;
use App\Enums\PermissionEnum;
use App\Exceptions\InvoiceExtractionException;
use App\Models\User;
use App\Repositories\ProductRepository;
use App\Repositories\StoreRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\TaxRepository;
use App\Repositories\UnitRepository;
use App\Services\Invoice\Extraction\DTO\ExtractedInvoice;
use App\Services\Invoice\Extraction\DTO\ExtractedLineItem;
use App\Services\PermissionService;
use App\Support\TextNormalizer;
use Illuminate\Http\UploadedFile;

/**
 * Orchestrates AI invoice extraction for the review page. Authorizes the store,
 * runs the configured extractor over the uploaded file, then matches the
 * extracted supplier / products / VAT against the store's records and returns a
 * prefilled review payload. Stateless: nothing is persisted and the file is not
 * retained — the existing createPurchaseInvoice mutation remains the only writer.
 */
class InvoiceExtractionService
{
    private const SUPPORTED_MIME = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    /** Normalized names that identify the store's VAT tax, to pair an extracted rate with a tax_id. */
    private const VAT_TAX_ALIASES = ['vat', 'thue vat', 'gtgt', 'thue gtgt', 'thue gia tri gia tang'];

    /** Tolerated rounding gap (in đồng) when reconciling extracted totals. */
    private const RECONCILE_TOLERANCE = 1.0;

    public function __construct(
        private ExtractorManager $manager,
        private PermissionService $permissionService,
        private SupplierRepository $supplierRepository,
        private ProductRepository $productRepository,
        private TaxRepository $taxRepository,
        private UnitRepository $unitRepository,
        private StoreRepository $storeRepository,
    ) {}

    /**
     * Read an uploaded purchase invoice into a prefilled review payload.
     *
     * @return array<string,mixed>
     */
    public function extractForReview(User $actor, int $storeId, UploadedFile $file, string $provider): array
    {
        $this->permissionService->authorizeStore($actor, PermissionEnum::CREATE_INVOICE, $storeId);

        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::SUPPORTED_MIME, true)) {
            throw new InvoiceExtractionException(
                ErrorCode::EXTRACTION_INVALID_FILE,
                'Unsupported file type. Upload a PDF or a photo (JPG, PNG or WEBP).'
            );
        }

        $extracted = $this->manager->driver($provider)->extract($file->getContent(), $mime);

        $businessId = (int) ($this->storeRepository->findById($storeId)?->business_id ?? 0);

        return $this->buildReview($extracted, $storeId, $businessId, $provider);
    }

    /**
     * @return array<string,mixed>
     */
    private function buildReview(ExtractedInvoice $extracted, int $storeId, int $businessId, string $provider): array
    {
        $vatTaxId = $this->resolveVatTaxId($storeId);

        $items = array_map(
            fn (ExtractedLineItem $item) => $this->buildItem($item, $storeId, $vatTaxId),
            $extracted->items,
        );

        $warnings = $extracted->warnings;
        if ($items === []) {
            $warnings[] = 'No line items could be read from this invoice — add them manually.';
        }
        $warnings = $this->reconcile($extracted, $items, $warnings);

        $supplier = $this->matchSupplier($extracted, $businessId);

        return [
            'supplier'    => $supplier,
            'invoice_no'  => $extracted->invoiceNo,
            'invoice_date' => $extracted->invoiceDate,
            'currency'    => $extracted->currency,
            'subtotal'    => $extracted->subtotal,
            'vat_total'   => $extracted->vatTotal,
            'grand_total' => $extracted->grandTotal,
            'items'       => $items,
            'warnings'    => array_values($warnings),
            'source'      => $provider,
            'suggest_ai'  => $this->shouldSuggestAi($provider, $supplier, $items),
        ];
    }

    /**
     * The free template path can read a clean invoice but stumble on a messy one.
     * When it comes back without a supplier or without any line items, suggest the
     * AI scan so the user gets a better read rather than fixing everything by hand.
     * The AI path never suggests itself.
     *
     * @param  array<string,mixed>  $supplier
     * @param  list<array<string,mixed>>  $items
     */
    private function shouldSuggestAi(string $provider, array $supplier, array $items): bool
    {
        if ($provider === 'gemini') {
            return false;
        }

        $noSupplier = ($supplier['extracted']['name'] ?? null) === null && $supplier['match'] === null;

        return $noSupplier || $items === [];
    }

    /**
     * Resolve the supplier (the seller) by MST then exact name. The extracted
     * contact fields always travel back for the review header and, when unmatched,
     * to prefill the create-supplier form; a matched supplier is never overwritten.
     *
     * @return array<string,mixed>
     */
    private function matchSupplier(ExtractedInvoice $e, int $businessId): array
    {
        $match = null;

        if ($e->supplierTaxCode !== null) {
            $byCode = $this->supplierRepository->findByTaxCode($businessId, $e->supplierTaxCode);
            if ($byCode) {
                $match = ['party_id' => (int) $byCode->party_id, 'name' => $byCode->name, 'matched_by' => 'tax_code'];
            }
        }

        if ($match === null && $e->supplierName !== null) {
            $byName = $this->supplierRepository->findByName($businessId, $e->supplierName);
            if ($byName) {
                $match = ['party_id' => (int) $byName->party_id, 'name' => $byName->name, 'matched_by' => 'name'];
            }
        }

        return [
            'extracted' => [
                'name'     => $e->supplierName,
                'tax_code' => $e->supplierTaxCode,
                'phone'    => $e->supplierPhone,
                'address'  => $e->supplierAddress,
            ],
            'match' => $match,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildItem(ExtractedLineItem $item, int $storeId, ?int $vatTaxId): array
    {
        $match = null;

        if ($item->code !== null) {
            $byCode = $this->productRepository->findByCode($storeId, $item->code);
            if ($byCode) {
                $match = ['product_id' => (int) $byCode->id, 'name' => $byCode->name, 'matched_by' => 'code'];
            }
        }

        if ($match === null) {
            $normalized = TextNormalizer::normalize($item->name);
            if ($normalized !== '') {
                $byName = $this->productRepository->findByNameNormalized($storeId, $normalized);
                if ($byName) {
                    $match = ['product_id' => (int) $byName->id, 'name' => $byName->name, 'matched_by' => 'name'];
                }
            }
        }

        // A line's VAT rate is entered per line, so pair the extracted rate with the
        // store's VAT tax id (rate lives on the line, not the tax record).
        $taxMatch = ($item->taxRate !== null && $vatTaxId !== null)
            ? ['tax_id' => $vatTaxId, 'rate' => $item->taxRate]
            : null;

        $unitMatch = null;
        if ($item->unit !== null) {
            $normalizedUnit = TextNormalizer::normalize($item->unit);
            if ($normalizedUnit !== '') {
                $unit = $this->unitRepository->findByNameNormalized($storeId, $normalizedUnit);
                if ($unit) {
                    $unitMatch = ['unit_id' => (int) $unit->id, 'name' => $unit->name];
                }
            }
        }

        return [
            'extracted' => [
                'name'       => $item->name,
                'code'       => $item->code,
                'unit'       => $item->unit,
                'quantity'   => $item->quantity,
                'unit_price' => $item->unitPrice,
                'tax_rate'   => $item->taxRate,
                'tax_amount' => $item->taxAmount,
                'line_total' => $item->lineTotal,
            ],
            'match'      => $match,
            'tax_match'  => $taxMatch,
            'unit_match' => $unitMatch,
        ];
    }

    private function resolveVatTaxId(int $storeId): ?int
    {
        $tax = $this->taxRepository->firstActiveByNormalizedNames($storeId, self::VAT_TAX_ALIASES);
        return $tax ? (int) $tax->id : null;
    }

    /**
     * Cross-check the extracted numbers and append a warning for anything that
     * doesn't add up, rather than silently trusting the OCR.
     *
     * @param  list<array<string,mixed>>  $items
     * @param  list<string>  $warnings
     * @return list<string>
     */
    private function reconcile(ExtractedInvoice $e, array $items, array $warnings): array
    {
        foreach ($items as $index => $item) {
            $ex = $item['extracted'];
            if ($ex['quantity'] !== null && $ex['unit_price'] !== null && $ex['line_total'] !== null) {
                $expected = round((float) $ex['quantity'] * (float) $ex['unit_price'], 2);
                if (abs($expected - (float) $ex['line_total']) > self::RECONCILE_TOLERANCE) {
                    $line = $index + 1;
                    $warnings[] = "Line {$line}: quantity × unit price ({$expected}) doesn't match the line total ({$ex['line_total']}).";
                }
            }
        }

        if ($e->subtotal !== null && $e->vatTotal !== null && $e->grandTotal !== null) {
            $expected = round($e->subtotal + $e->vatTotal, 2);
            if (abs($expected - $e->grandTotal) > self::RECONCILE_TOLERANCE) {
                $warnings[] = "Subtotal + VAT ({$expected}) doesn't match the grand total ({$e->grandTotal}).";
            }
        }

        return $warnings;
    }
}
