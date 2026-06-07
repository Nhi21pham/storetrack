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
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Tax;
use App\Models\User;
use App\Repositories\Invoice\InventoryBatchRepository;
use App\Repositories\Invoice\InvoiceRepository;
use App\Repositories\Invoice\InvoiceSequenceRepository;
use App\Services\AuditLog\Loggers\InvoiceAuditLogger;
use App\Services\PermissionService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        private InvoiceRepository $invoiceRepository,
        private InvoiceSequenceRepository $sequenceRepository,
        private InventoryBatchRepository $batchRepository,
        private InventoryCostingService $costingService,
        private PermissionService $permissionService,
        private InvoiceAuditLogger $auditLogger,
    ) {}

    public function getAll(User $user, int $storeId, ?string $type = null): Collection
    {
        $this->permissionService->authorizeStore($user, PermissionEnum::UPDATE_INVOICE, $storeId);
        return $this->invoiceRepository->all($storeId, $type);
    }

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

    public function updatePurchase(User $actor, int $id, array $data): Invoice
    {
        $items = $data['items'] ?? [];
        if (empty($items)) {
            throw new InvoiceException(ErrorCode::INVOICE_NO_ITEMS, 'An invoice must have at least one item.');
        }

        return DB::transaction(function () use ($actor, $id, $data, $items) {
            $invoice = $this->invoiceRepository->lockById($id);
            if (!$invoice) {
                throw new InvoiceException(ErrorCode::INVOICE_NOT_FOUND, 'Invoice not found.');
            }
            $storeId = (int) $invoice->store_id;
            $this->permissionService->authorizeStore($actor, PermissionEnum::UPDATE_INVOICE, $storeId);
            $this->assertPurchase($invoice);
            $this->assertSupplier((int) $data['party_id'], $storeId);

            // Reverse the invoice's old stock effects, then re-apply the new lines from scratch.
            $this->reversePurchaseStock($invoice);
            $this->invoiceRepository->deleteItems($invoice);

            $this->invoiceRepository->update($invoice, [
                'party_id'       => (int) $data['party_id'],
                'description'    => $data['description'] ?? null,
                'invoice_date'   => $data['invoice_date'],
                'payment_method' => $data['payment_method'],
                'payment_status' => $data['payment_status'] ?? InvoicePaymentStatusEnum::UNPAID->value,
            ]);

            $subtotal = 0.0;
            $taxTotal = 0.0;
            foreach ($items as $item) {
                $line = $this->addPurchaseLine($invoice, $storeId, $item, (string) $data['invoice_date']);
                $subtotal += (float) $line->subtotal;
                $taxTotal += (float) $line->tax_total;
            }

            $invoice = $this->applyTotals($invoice, $subtotal, $taxTotal);
            $this->auditLogger->invoiceUpdated($actor, $invoice);

            return $this->invoiceRepository->findById((int) $invoice->id);
        });
    }

    public function delete(User $actor, int $id): void
    {
        DB::transaction(function () use ($actor, $id) {
            $invoice = $this->invoiceRepository->lockById($id);
            if (!$invoice) {
                throw new InvoiceException(ErrorCode::INVOICE_NOT_FOUND, 'Invoice not found.');
            }
            $storeId = (int) $invoice->store_id;
            $this->permissionService->authorizeStore($actor, PermissionEnum::DELETE_INVOICE, $storeId);
            $this->assertPurchase($invoice);

            $this->reversePurchaseStock($invoice);

            $invoiceId = (int) $invoice->id;
            $code = (string) $invoice->code;
            $type = $invoice->type->value;

            $this->invoiceRepository->delete($invoice);
            $this->auditLogger->invoiceDeleted($actor, $invoiceId, $code, $type, $storeId);
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
        // Suppliers are business-scoped — a supplier in the store's business can
        // be invoiced even if it isn't linked to this specific store.
        $businessId = Store::whereKey($storeId)->value('business_id');

        $exists = Supplier::query()
            ->where('party_id', $partyId)
            ->where('business_id', $businessId)
            ->exists();

        if (!$exists) {
            throw new InvoiceException(
                ErrorCode::INVOICE_PARTY_INVALID,
                'Supplier not found in this business.'
            );
        }
    }

    /** Removes the batches + stock this purchase created. Blocks if any has been sold. */
    private function reversePurchaseStock(Invoice $invoice): void
    {
        $batches = $this->batchRepository->lockBySourceInvoice((int) $invoice->id);

        foreach ($batches as $batch) {
            if ((float) $batch->quantity_remaining < (float) $batch->quantity_received) {
                throw new InvoiceException(
                    ErrorCode::INVOICE_STOCK_CONSUMED,
                    'Some items from this invoice have already been sold, so it can no longer be edited or deleted.'
                );
            }
        }

        foreach ($batches as $batch) {
            $this->costingService->removeBatch($batch);
        }
    }

    private function assertPurchase(Invoice $invoice): void
    {
        if ($invoice->type !== InvoiceTypeEnum::PURCHASE) {
            throw new InvoiceException(
                ErrorCode::INVOICE_IMMUTABLE,
                'Only purchase invoices can be edited or deleted here.'
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
