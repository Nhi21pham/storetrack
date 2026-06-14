<?php

namespace App\Services\Invoice;

use App\Enums\ErrorCode;
use App\Enums\ExportScope;
use App\Enums\InvoicePaymentMethodEnum;
use App\Enums\InvoicePaymentStatusEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\PermissionEnum;
use App\Exceptions\InvoiceException;
use App\Exceptions\TaxException;
use App\Exports\InvoiceExport;
use App\Jobs\Exports\ExportInvoiceJob;
use App\Models\Export;
use App\Models\Invoice\Invoice;
use App\Models\Invoice\InvoiceProduct;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tax;
use App\Models\User;
use App\Repositories\Invoice\InventoryBatchRepository;
use App\Repositories\Invoice\InvoiceRepository;
use App\Repositories\Invoice\InvoiceSequenceRepository;
use App\Repositories\Invoice\ProductStockRepository;
use App\Repositories\Payment\PaymentAllocationRepository;
use App\Services\AuditLog\Loggers\InvoiceAuditLogger;
use App\Services\ExportService;
use App\Services\Invoice\Stock\InvoiceStockHandler;
use App\Services\Invoice\Stock\InvoiceStockHandlerFactory;
use App\Services\Payment\PaymentService;
use App\Services\PermissionService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        private InvoiceRepository $invoiceRepository,
        private InvoiceSequenceRepository $sequenceRepository,
        private InvoiceStockHandlerFactory $stockHandlerFactory,
        private ProductStockRepository $stockRepository,
        private InventoryBatchRepository $batchRepository,
        private PermissionService $permissionService,
        private InvoiceAuditLogger $auditLogger,
        private ExportService $exportService,
        private PaymentService $paymentService,
        private PaymentAllocationRepository $allocationRepository,
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

    public function getStockLevels(User $user, int $storeId): Collection
    {
        $this->permissionService->authorizeStore($user, PermissionEnum::UPDATE_INVOICE, $storeId);
        return $this->stockRepository->allForStore($storeId);
    }

    public function getOpenBatches(User $user, int $storeId): Collection
    {
        $this->permissionService->authorizeStore($user, PermissionEnum::UPDATE_INVOICE, $storeId);
        return $this->batchRepository->openForStore($storeId);
    }

    public function createPurchase(User $actor, int $storeId, array $data): Invoice
    {
        return $this->create($actor, $storeId, InvoiceTypeEnum::PURCHASE, $data);
    }

    public function createSale(User $actor, int $storeId, array $data): Invoice
    {
        return $this->create($actor, $storeId, InvoiceTypeEnum::SALE, $data);
    }

    public function updatePurchase(User $actor, int $id, array $data): Invoice
    {
        return $this->update($actor, $id, InvoiceTypeEnum::PURCHASE, $data);
    }

    public function updateSale(User $actor, int $id, array $data): Invoice
    {
        return $this->update($actor, $id, InvoiceTypeEnum::SALE, $data);
    }

    private function create(User $actor, int $storeId, InvoiceTypeEnum $type, array $data): Invoice
    {
        $this->permissionService->authorizeStore($actor, PermissionEnum::CREATE_INVOICE, $storeId);

        $items = $data['items'] ?? [];
        if (empty($items)) {
            throw new InvoiceException(ErrorCode::INVOICE_NO_ITEMS, 'An invoice must have at least one item.');
        }

        $handler = $this->stockHandlerFactory->for($type);

        return DB::transaction(function () use ($actor, $storeId, $type, $data, $items, $handler) {
            $handler->assertParty((int) $data['party_id'], $storeId);
            $handler->linkPartyToStore((int) $data['party_id'], $storeId);

            $invoice = $this->createHeader($actor, $storeId, $type, $data);

            [$subtotal, $taxTotal] = $this->addLines($invoice, $storeId, $handler, $items, (string) $data['invoice_date']);

            $invoice = $this->applyTotals($invoice, $subtotal, $taxTotal);
            $this->auditLogger->invoiceCreated($actor, $invoice);

            $this->applyPaymentIntent($actor, $storeId, $invoice, $data);

            return $this->invoiceRepository->findById((int) $invoice->id);
        });
    }

    private function update(User $actor, int $id, InvoiceTypeEnum $type, array $data): Invoice
    {
        $items = $data['items'] ?? [];
        if (empty($items)) {
            throw new InvoiceException(ErrorCode::INVOICE_NO_ITEMS, 'An invoice must have at least one item.');
        }

        $handler = $this->stockHandlerFactory->for($type);

        return DB::transaction(function () use ($actor, $id, $type, $data, $items, $handler) {
            $invoice = $this->invoiceRepository->lockById($id);
            if (!$invoice) {
                throw new InvoiceException(ErrorCode::INVOICE_NOT_FOUND, 'Invoice not found.');
            }
            $storeId = (int) $invoice->store_id;
            $this->permissionService->authorizeStore($actor, PermissionEnum::UPDATE_INVOICE, $storeId);
            $this->assertType($invoice, $type);
            $handler->assertParty((int) $data['party_id'], $storeId);
            $handler->linkPartyToStore((int) $data['party_id'], $storeId);

            // Reverse the invoice's old stock effects, then re-apply the new lines. Existing
            // payments are KEPT — the status re-derives from paid_amount vs the new total
            // (e.g. a fully-paid 200 invoice edited up to 210 becomes PARTIAL, 10 owed).
            $handler->reverse($invoice);
            $this->invoiceRepository->deleteItems($invoice);

            $this->invoiceRepository->update($invoice, [
                'party_id'       => (int) $data['party_id'],
                'description'    => $data['description'] ?? null,
                'invoice_date'   => $data['invoice_date'],
                'payment_method' => $data['payment_method'],
            ]);

            [$subtotal, $taxTotal] = $this->addLines($invoice, $storeId, $handler, $items, (string) $data['invoice_date']);

            $invoice = $this->applyTotals($invoice, $subtotal, $taxTotal);
            $this->invoiceRepository->update($invoice, [
                'payment_status' => InvoicePaymentStatusEnum::fromAmounts((float) $invoice->paid_amount, (float) $invoice->grand_total)->value,
            ]);
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
            $this->assertNoPayments($invoice);

            $this->stockHandlerFactory->for($invoice->type)->reverse($invoice);

            $invoiceId = (int) $invoice->id;
            $code = (string) $invoice->code;
            $type = $invoice->type->value;

            $this->invoiceRepository->delete($invoice);
            $this->auditLogger->invoiceDeleted($actor, $invoiceId, $code, $type, $storeId);
        });
    }

    public function queueExport(User $user, int $storeId, array $filters = [], ?string $clientId = null): Export
    {
        $this->permissionService->authorizeStore($user, PermissionEnum::UPDATE_INVOICE, $storeId);

        $type              = ExportInvoiceJob::TYPE;
        $scope             = ExportScope::STORE;
        $scopeName         = Store::find($storeId)?->name;
        $normalizedFilters = $this->normalizeExportFilters($filters);
        $jobClass          = ExportInvoiceJob::class;

        return $this->exportService->queue(
            $user,
            $type,
            $scope,
            $storeId,
            $scopeName,
            $normalizedFilters,
            $jobClass,
            $clientId,
        );
    }

    private function normalizeExportFilters(array $filters): array
    {
        $clean = [];

        $invoiceType = InvoiceTypeEnum::tryFrom(strtolower((string) ($filters['type'] ?? '')));
        if ($invoiceType !== null) {
            $clean['type'] = $invoiceType->value;
        }

        if (!empty($filters['search'])) {
            $clean['search'] = (string) $filters['search'];
        }

        $paymentMethod = InvoicePaymentMethodEnum::tryFrom(strtolower((string) ($filters['payment_method'] ?? '')));
        if ($paymentMethod !== null) {
            $clean['payment_method'] = $paymentMethod->value;
        }

        $paymentStatus = InvoicePaymentStatusEnum::tryFrom(strtolower((string) ($filters['payment_status'] ?? '')));
        if ($paymentStatus !== null) {
            $clean['payment_status'] = $paymentStatus->value;
        }

        if (!empty($filters['party_id'])) {
            $clean['party_id'] = (int) $filters['party_id'];
        }

        if (!empty($filters['start_date'])) {
            $clean['start_date'] = (string) $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $clean['end_date'] = (string) $filters['end_date'];
        }

        if (is_array($filters['ids'] ?? null) && count($filters['ids']) > 0) {
            $clean['ids'] = array_values(array_map('intval', $filters['ids']));
        }

        if (is_array($filters['columns'] ?? null)) {
            $columns = array_values(array_filter(
                $filters['columns'],
                fn ($column) => in_array($column, InvoiceExport::COLUMN_KEYS, true),
            ));
            if ($columns !== []) {
                $clean['columns'] = $columns;
            }
        }

        return $clean;
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
            'payment_status' => InvoicePaymentStatusEnum::UNPAID->value,
            'subtotal'       => 0,
            'tax_total'      => 0,
            'grand_total'    => 0,
            'paid_amount'    => 0,
        ]);
    }

    /**
     * Apply the form's payment intent (Unpaid / Partial / Paid + amount) by recording
     * a payment against the invoice. Used on create, and on edit after the invoice's
     * prior payments have been released. Status stays derived from the resulting paid_amount.
     */
    private function applyPaymentIntent(User $actor, int $storeId, Invoice $invoice, array $data): void
    {
        $grandTotal = (float) $invoice->grand_total;
        if ($grandTotal <= 0) {
            return;
        }

        $status = $data['payment_status'] ?? InvoicePaymentStatusEnum::UNPAID->value;
        if ($status === InvoicePaymentStatusEnum::PAID->value) {
            $amount = $grandTotal;
        } elseif ($status === InvoicePaymentStatusEnum::PARTIAL->value) {
            // A "partial" that happens to cover the whole invoice just settles it.
            $amount = min(round((float) ($data['paid_amount'] ?? 0), 2), $grandTotal);
        } else {
            return;
        }

        if ($amount <= 0) {
            return;
        }

        $this->paymentService->record($actor, $storeId, [
            'party_id'    => (int) $invoice->party_id,
            'paid_at'     => $data['invoice_date'],
            'method'      => $data['payment_method'],
            'allocations' => [
                ['invoice_id' => (int) $invoice->id, 'amount' => $amount],
            ],
        ]);
    }

    /** An invoice with payments allocated against it can't be edited or deleted until they're removed. */
    private function assertNoPayments(Invoice $invoice): void
    {
        if ($this->allocationRepository->existsForInvoice((int) $invoice->id)) {
            throw new InvoiceException(
                ErrorCode::INVOICE_HAS_PAYMENTS,
                'This invoice has payments recorded against it. Remove the payments before editing or deleting it.'
            );
        }
    }

    /** Creates each line (tax snapshot + stock effect) and returns the [subtotal, taxTotal] running totals. */
    private function addLines(Invoice $invoice, int $storeId, InvoiceStockHandler $handler, array $items, string $invoiceDate): array
    {
        $subtotal = 0.0;
        $taxTotal = 0.0;
        foreach ($items as $item) {
            $line = $this->addLine($invoice, $storeId, $handler, $item, $invoiceDate);
            $subtotal += (float) $line->subtotal;
            $taxTotal += (float) $line->tax_total;
        }
        return [$subtotal, $taxTotal];
    }

    /** Creates one invoice line (with its tax snapshot) and applies its stock effect via the handler. */
    private function addLine(Invoice $invoice, int $storeId, InvoiceStockHandler $handler, array $item, string $invoiceDate): InvoiceProduct
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

        $handler->applyLine($invoice, $line, $product, $quantity, $unitPrice, $invoiceDate);

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

    private function assertType(Invoice $invoice, InvoiceTypeEnum $type): void
    {
        if ($invoice->type !== $type) {
            throw new InvoiceException(
                ErrorCode::INVOICE_IMMUTABLE,
                'This invoice cannot be edited as a ' . $type->value . ' invoice.'
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
