<?php

namespace App\Services\Invoice;

use App\Enums\ErrorCode;
use App\Enums\InvoicePaymentStatusEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\PermissionEnum;
use App\Exceptions\InvoiceException;
use App\Exceptions\TaxException;
use App\Models\Invoice\Invoice;
use App\Models\Invoice\InvoiceProduct;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Tax;
use App\Models\User;
use App\Repositories\Invoice\InvoiceRepository;
use App\Repositories\Invoice\InvoiceSequenceRepository;
use App\Services\AuditLog\Loggers\InvoiceAuditLogger;
use App\Services\PermissionService;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        private InvoiceRepository $invoiceRepository,
        private InvoiceSequenceRepository $sequenceRepository,
        private InventoryCostingService $costingService,
        private PermissionService $permissionService,
        private InvoiceAuditLogger $auditLogger,
    ) {}

    public function getById(User $user, int $id): Invoice
    {
        $invoice = $this->invoiceRepository->findById($id);
        if (!$invoice) {
            throw new InvoiceException(ErrorCode::INVOICE_NOT_FOUND, 'Invoice not found.');
        }
        $this->permissionService->authorizeStore($user, PermissionEnum::UPDATE_INVOICE, (int) $invoice->store_id);
        return $invoice;
    }

    public function createPurchase(User $actor, int $storeId, array $data): Invoice
    {
        $this->permissionService->authorizeStore($actor, PermissionEnum::CREATE_INVOICE, $storeId);

        $items = $data['items'] ?? [];
        if (empty($items)) {
            throw new InvoiceException(ErrorCode::INVOICE_NO_ITEMS, 'An invoice must have at least one item.');
        }

        return DB::transaction(function () use ($actor, $storeId, $data, $items) {
            $this->assertSupplier((int) $data['party_id'], $storeId);

            $invoice = $this->createHeader($actor, $storeId, InvoiceTypeEnum::PURCHASE, $data);

            $subtotal = 0.0;
            $taxTotal = 0.0;
            foreach ($items as $item) {
                $line = $this->addPurchaseLine($invoice, $storeId, $item, (string) $data['invoice_date']);
                $subtotal += (float) $line->subtotal;
                $taxTotal += (float) $line->tax_total;
            }

            $invoice = $this->applyTotals($invoice, $subtotal, $taxTotal);
            $this->auditLogger->invoiceCreated($actor, $invoice);

            return $this->invoiceRepository->findById((int) $invoice->id);
        });
    }

    private function createHeader(User $actor, int $storeId, InvoiceTypeEnum $type, array $data): Invoice
    {
        $number = $this->sequenceRepository->nextNumber($storeId, $type);
        $code = $type->codePrefix() . sprintf('%06d', $number);

        return $this->invoiceRepository->create([
            'store_id'       => $storeId,
            'type'           => $type->value,
            'code'           => $code,
            'party_id'       => (int) $data['party_id'],
            'created_by'     => (int) $actor->id,
            'description'    => $data['description'] ?? null,
            'invoice_date'   => $data['invoice_date'],
            'payment_method' => $data['payment_method'],
            'payment_status' => $data['payment_status'] ?? InvoicePaymentStatusEnum::UNPAID->value,
            'subtotal'       => 0,
            'tax_total'      => 0,
            'grand_total'    => 0,
        ]);
    }

    /** Creates one purchase line (with its tax snapshot) and fills a FIFO stock batch from it. */
    private function addPurchaseLine(Invoice $invoice, int $storeId, array $item, string $invoiceDate): InvoiceProduct
    {
        $product   = $this->assertProduct((int) $item['product_id'], $storeId);
        $quantity  = (float) $item['quantity'];
        $unitPrice = (float) $item['unit_price'];
        $subtotal  = round($quantity * $unitPrice, 2);

        $line = $this->invoiceRepository->createItem([
            'invoice_id'   => (int) $invoice->id,
            'product_id'   => (int) $product->id,
            'product_name' => $product->name,
            'quantity'     => $quantity,
            'unit_price'   => $unitPrice,
            'subtotal'     => $subtotal,
            'tax_total'    => 0,
            'grand_total'  => $subtotal,
        ]);

        $taxTotal = $this->snapshotLineTaxes($line, $storeId, $item['taxes'] ?? [], $subtotal);
        if ($taxTotal > 0) {
            $line = $this->invoiceRepository->updateItemTotals($line, [
                'tax_total'   => $taxTotal,
                'grand_total' => round($subtotal + $taxTotal, 2),
            ]);
        }

        // The purchase price is the net unit cost; it fills a FIFO batch and raises stock.
        $this->costingService->addBatch(
            $storeId,
            (int) $product->id,
            (int) $invoice->id,
            (int) $line->id,
            $unitPrice,
            $quantity,
            $invoiceDate,
        );

        return $line;
    }

    /** Snapshots the taxes entered on a line and returns the line's total tax amount. */
    private function snapshotLineTaxes(InvoiceProduct $line, int $storeId, array $taxes, float $lineSubtotal): float
    {
        $total = 0.0;
        foreach ($taxes as $taxInput) {
            $tax    = $this->resolveTax((int) $taxInput['tax_id'], $storeId);
            $rate   = (float) $taxInput['rate'];
            $amount = round($lineSubtotal * $rate / 100, 2);

            $this->invoiceRepository->createItemTax([
                'invoice_product_id' => (int) $line->id,
                'tax_id'             => (int) $tax->id,
                'tax_name'           => $tax->name,
                'tax_rate'           => $rate,
                'tax_amount'         => $amount,
            ]);

            $total += $amount;
        }
        return round($total, 2);
    }

    private function applyTotals(Invoice $invoice, float $subtotal, float $taxTotal): Invoice
    {
        return $this->invoiceRepository->updateTotals($invoice, [
            'subtotal'    => round($subtotal, 2),
            'tax_total'   => round($taxTotal, 2),
            'grand_total' => round($subtotal + $taxTotal, 2),
        ]);
    }

    private function assertSupplier(int $partyId, int $storeId): void
    {
        $supplier = Supplier::query()
            ->where('party_id', $partyId)
            ->whereHas('stores', fn ($q) => $q->where('stores.id', $storeId))
            ->first();

        if (!$supplier) {
            throw new InvoiceException(
                ErrorCode::INVOICE_PARTY_INVALID,
                'Supplier not found in this store.'
            );
        }
    }

    private function assertProduct(int $productId, int $storeId): Product
    {
        $product = Product::query()->where('id', $productId)->sharedLock()->first();

        if (!$product || (int) $product->store_id !== $storeId) {
            throw new InvoiceException(
                ErrorCode::INVOICE_PRODUCT_INVALID,
                'Product not found in this store.'
            );
        }
        if (!$product->is_active) {
            throw new InvoiceException(
                ErrorCode::INVOICE_PRODUCT_INVALID,
                "Product '{$product->name}' is inactive."
            );
        }

        return $product;
    }

    private function resolveTax(int $taxId, int $storeId): Tax
    {
        $tax = Tax::query()->where('id', $taxId)->first();

        if (!$tax || (int) $tax->store_id !== $storeId) {
            throw new TaxException(ErrorCode::TAX_NOT_FOUND, 'Tax not found in this store.');
        }

        return $tax;
    }
}
