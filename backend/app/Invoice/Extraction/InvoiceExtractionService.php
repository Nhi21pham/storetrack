<?php

namespace App\Invoice\Extraction;

use App\Enums\ErrorCode;
use App\Enums\PermissionEnum;
use App\Exceptions\InvoiceExtractionException;
use App\Models\InvoiceScan;
use App\Models\User;
use App\Repositories\InvoiceScanRepository;
use App\Repositories\ProductRepository;
use App\Repositories\StoreRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\TaxRepository;
use App\Repositories\UnitRepository;
use App\Invoice\Extraction\DTO\ExtractedInvoice;
use App\Invoice\Extraction\DTO\ExtractedLineItem;
use App\Services\PermissionService;
use App\Support\TextNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates AI invoice extraction for the review page. Authorizes the store,
 * runs the configured extractor over the uploaded file, then matches the
 * extracted supplier / products / VAT against the store's records and returns a
 * prefilled review payload. The existing createPurchaseInvoice mutation remains
 * the only writer of invoices.
 *
 * Each scan is logged to a lightweight invoice_scans history record (engine used,
 * who/when, a summary of what was read, and any entities the user creates during
 * review) — metadata only; the uploaded file itself is never retained.
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
        private InvoiceScanRepository $invoiceScanRepository,
    ) {}

    /**
     * Read an uploaded purchase invoice into a prefilled review payload.
     *
     * @return array<string,mixed>
     */
    public function extractForReview(
        User $actor,
        int $storeId,
        UploadedFile $file,
        string $provider,
        string $type
    ): array {
        $this->permissionService->authorizeStore($actor, PermissionEnum::CREATE_INVOICE, $storeId);

        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::SUPPORTED_MIME, true)) {
            throw new InvoiceExtractionException(
                ErrorCode::EXTRACTION_INVALID_FILE,
                'Unsupported file type. Upload a PDF or a photo (JPG, PNG or WEBP).'
            );
        }

        $filename = $file->getClientOriginalName();

        try {
            $extracted = $this->manager->driver($provider)->extract($file->getContent(), $mime);
        } catch (InvoiceExtractionException $e) {
            // EXTRACTION_NEEDS_AI is the free parser stepping aside for the AI scan,
            // not a failure — don't log it. Anything else is a genuine failed scan.
            if ($e->getErrorCode() !== ErrorCode::EXTRACTION_NEEDS_AI) {
                $this->recordFailedScan($actor->id, $storeId, $type, $provider, $filename, $e->getMessage());
            }
            throw $e;
        }

        $businessId = (int) ($this->storeRepository->findById($storeId)?->business_id ?? 0);

        $review = $this->buildReview($extracted, $storeId, $businessId, $provider);

        // Returned to the client so it can attribute any entity it creates during
        // review back to this scan via recordCreatedEntity().
        $review['scan_id'] = $this->recordCompletedScan($actor->id, $storeId, $type, $provider, $filename, $review);

        return $review;
    }

    /**
     * Shaped, paginated scan history for a store, optionally filtered by engine,
     * status and a created-at date range. Any store member may view.
     *
     * @return array{data: list<array<string,mixed>>, total: int, current_page: int, last_page: int, per_page: int}
     */
    public function scanHistory(
        User $actor,
        int $storeId,
        ?string $type,
        ?string $scanType,
        ?string $status,
        ?string $startDate,
        ?string $endDate,
        int $perPage = 20
    ): array {
        $this->permissionService->authorizeStoreAccess($actor, $storeId);

        $paginator = $this->invoiceScanRepository->paginateForStore(
            $storeId,
            $type,
            $scanType,
            $status,
            $startDate,
            $endDate,
            min(max($perPage, 1), 100),
        );

        return [
            'data'         => array_map(fn (InvoiceScan $scan) => $this->toListItem($scan), $paginator->items()),
            'total'        => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
        ];
    }

    /**
     * One scan for the history detail view, gated by store membership (not the
     * scanning user) so any member can inspect another member's scan.
     */
    public function getScanForStore(User $actor, int $storeId, int $scanId): InvoiceScan
    {
        $this->permissionService->authorizeStoreAccess($actor, $storeId);

        $scan = $this->invoiceScanRepository->findForStore($scanId, $storeId);
        if (! $scan) {
            throw new InvoiceExtractionException(ErrorCode::NOT_FOUND, 'Scan not found.');
        }

        return $scan;
    }

    /**
     * Attribute an entity (supplier / product / unit) the user created during the
     * review step back to its scan, so the history shows what a scan led to.
     * Idempotent — re-recording the same entity is a no-op.
     */
    public function recordCreatedEntity(User $actor, int $storeId, int $scanId, string $type, int $entityId, string $name): void
    {
        $this->permissionService->authorizeStore($actor, PermissionEnum::CREATE_INVOICE, $storeId);

        $scan = $this->invoiceScanRepository->findForStore($scanId, $storeId);
        if (! $scan) {
            throw new InvoiceExtractionException(ErrorCode::NOT_FOUND, 'Scan not found.');
        }

        $metadata = $scan->metadata ?? [];
        $created = $metadata['created_entities'] ?? [];

        foreach ($created as $entry) {
            if (($entry['type'] ?? null) === $type && (int) ($entry['id'] ?? 0) === $entityId) {
                return;
            }
        }

        $created[] = ['type' => $type, 'id' => $entityId, 'name' => $name];
        $metadata['created_entities'] = $created;

        $this->invoiceScanRepository->update($scan, ['metadata' => $metadata]);
    }

    /**
     * Persist a completed scan and return its id (for created-entity attribution).
     * Best-effort: a logging hiccup must never cost the user their extraction.
     *
     * @param  array<string,mixed>  $review
     */
    private function recordCompletedScan(int $userId, int $storeId, string $type, string $provider, ?string $filename, array $review): ?int
    {
        try {
            $scan = $this->invoiceScanRepository->create([
                'user_id'           => $userId,
                'store_id'          => $storeId,
                'type'              => $type,
                'scan_type'         => $this->scanTypeFor($provider),
                'status'            => InvoiceScan::STATUS_COMPLETED,
                'original_filename' => $filename,
                'metadata'          => $this->buildScanMetadata($review),
            ]);

            return (int) $scan->id;
        } catch (\Throwable $e) {
            Log::error('Failed to record invoice scan history', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Persist a failed scan. Best-effort for the same reason as the success path —
     * the original extraction exception is what the caller re-throws.
     */
    private function recordFailedScan(int $userId, int $storeId, string $type, string $provider, ?string $filename, string $message): void
    {
        try {
            $this->invoiceScanRepository->create([
                'user_id'           => $userId,
                'store_id'          => $storeId,
                'type'              => $type,
                'scan_type'         => $this->scanTypeFor($provider),
                'status'            => InvoiceScan::STATUS_FAILED,
                'original_filename' => $filename,
                'error_message'     => $message,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to record invoice scan history', ['message' => $e->getMessage()]);
        }
    }

    private function scanTypeFor(string $provider): string
    {
        return $provider === 'gemini' ? InvoiceScan::SCAN_TYPE_AI : InvoiceScan::SCAN_TYPE_TEMPLATE;
    }

    /**
     * Compact, file-free summary of what a scan read, stored on the history row
     * and shown in the expanded detail.
     *
     * @param  array<string,mixed>  $review
     * @return array<string,mixed>
     */
    private function buildScanMetadata(array $review): array
    {
        $supplier = $review['supplier'] ?? [];
        $match = $supplier['match'] ?? null;
        $extracted = $supplier['extracted'] ?? [];

        $items = [];
        $matchedItems = 0;
        foreach ($review['items'] ?? [] as $item) {
            $ex = $item['extracted'] ?? [];
            $matched = ($item['match'] ?? null) !== null;
            if ($matched) {
                $matchedItems++;
            }
            $items[] = [
                'name'       => $ex['name'] ?? null,
                'code'       => $ex['code'] ?? null,
                'unit'       => $ex['unit'] ?? null,
                'quantity'   => $ex['quantity'] ?? null,
                'unit_price' => $ex['unit_price'] ?? null,
                'line_total' => $ex['line_total'] ?? null,
                'matched'    => $matched,
            ];
        }

        return [
            'supplier' => [
                'extracted_name' => $extracted['name'] ?? null,
                'matched_name'   => $match['name'] ?? null,
                'matched'        => $match !== null,
                'matched_by'     => $match['matched_by'] ?? null,
            ],
            'invoice_no'         => $review['invoice_no'] ?? null,
            'totals'             => [
                'subtotal'    => $review['subtotal'] ?? null,
                'vat_total'   => $review['vat_total'] ?? null,
                'grand_total' => $review['grand_total'] ?? null,
            ],
            'item_count'         => count($items),
            'matched_item_count' => $matchedItems,
            'warnings'           => $review['warnings'] ?? [],
            'items'              => $items,
            'created_entities'   => [],
        ];
    }

    /**
     * History-row projection for the board list — display fields pulled from the
     * stored metadata (the full items/warnings detail comes from getScanForStore()).
     *
     * @return array<string,mixed>
     */
    private function toListItem(InvoiceScan $scan): array
    {
        $metadata = $scan->metadata ?? [];
        $supplier = $metadata['supplier'] ?? [];
        $totals = $metadata['totals'] ?? [];

        return [
            'id'                 => $scan->id,
            'type'               => $scan->type,
            'scan_type'          => $scan->scan_type,
            'status'             => $scan->status,
            'original_filename'  => $scan->original_filename,
            'supplier_name'      => $supplier['matched_name'] ?? $supplier['extracted_name'] ?? null,
            'invoice_no'         => $metadata['invoice_no'] ?? null,
            'item_count'         => $metadata['item_count'] ?? 0,
            'matched_item_count' => $metadata['matched_item_count'] ?? 0,
            'created_count'      => count($metadata['created_entities'] ?? []),
            'grand_total'        => $totals['grand_total'] ?? null,
            'error_message'      => $scan->error_message,
            'user_name'          => $scan->user?->name,
            'user_email'         => $scan->user?->email,
            'created_at'         => optional($scan->created_at)->toIso8601String(),
        ];
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
