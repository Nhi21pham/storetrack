<?php

namespace App\Services\Report;

use App\Enums\ExportScope;
use App\Enums\PermissionEnum;
use App\Exports\Report\SaleReportExport;
use App\Jobs\Exports\ExportSaleReportJob;
use App\Models\Business;
use App\Models\Export;
use App\Models\Invoice\InvoiceProductCost;
use App\Models\Store;
use App\Models\User;
use App\Repositories\Report\SaleReportRepository;
use App\Services\ExportService;
use App\Services\PermissionService;

class SaleReportService
{
    public function __construct(
        private SaleReportRepository $saleReportRepository,
        private PermissionService $permissionService,
        private ExportService $exportService,
    ) {}

    /**
     * The full sale report for a store: one row per sold product line. The
     * frontend filters/searches/sorts these client-side, mirroring the invoice list.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getReport(User $user, int $storeId): array
    {
        $this->permissionService->authorizeStore($user, PermissionEnum::UPDATE_INVOICE, $storeId);

        return $this->saleReportRepository->all($storeId)
            ->map(fn (InvoiceProductCost $item) => $this->toRow($item))
            ->all();
    }

    /**
     * The consolidated sale report across every store in a business: one row per
     * sold line, each carrying its store. Owner-only.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBusinessReport(User $user, int $businessId): array
    {
        $this->permissionService->authorizeBusinessOwner($user, $businessId);

        return $this->saleReportRepository->allForBusiness($businessId)
            ->map(fn (InvoiceProductCost $item) => $this->toRow($item))
            ->all();
    }

    public function queueExport(User $user, int $storeId, array $filters = [], ?string $clientId = null): Export
    {
        $this->permissionService->authorizeStore($user, PermissionEnum::UPDATE_INVOICE, $storeId);

        return $this->exportService->queue(
            $user,
            ExportSaleReportJob::TYPE_STORE,
            ExportScope::STORE,
            $storeId,
            Store::find($storeId)?->name,
            $this->normalizeExportFilters($filters),
            ExportSaleReportJob::class,
            $clientId,
        );
    }

    public function queueBusinessExport(User $user, int $businessId, array $filters = [], ?string $clientId = null): Export
    {
        $this->permissionService->authorizeBusinessOwner($user, $businessId);

        return $this->exportService->queue(
            $user,
            ExportSaleReportJob::TYPE_BUSINESS,
            ExportScope::BUSINESS,
            $businessId,
            Business::find($businessId)?->name,
            $this->normalizeExportFilters($filters),
            ExportSaleReportJob::class,
            $clientId,
        );
    }

    /** Projects a sold batch-slice into the report row shape exposed by GraphQL. */
    private function toRow(InvoiceProductCost $item): array
    {
        $line = $item->invoiceProduct;
        $invoice = $line?->invoice;
        $batch = $item->batch;

        $quantity = (float) $item->quantity;
        $unitPrice = (float) ($line?->unit_price ?? 0);

        return [
            'id'                    => (int) $item->id,
            'store_id'              => $invoice?->store_id !== null ? (int) $invoice->store_id : null,
            'store_name'            => $invoice?->relationLoaded('store') ? $invoice->store?->name : null,
            'product_id'            => $line?->product_id !== null ? (int) $line->product_id : null,
            'product_name'          => $line?->product?->name ?? $line?->product_name,
            'product_code'          => $line?->product?->code,
            'tags'                  => $line?->product?->tags ?? [],
            'customer_party_id'     => $invoice?->party_id !== null ? (int) $invoice->party_id : null,
            'customer_name'         => $invoice?->party_name,
            'invoice_id'            => $line?->invoice_id !== null ? (int) $line->invoice_id : null,
            'invoice_code'          => $invoice?->code,
            'invoice_date'          => $invoice?->invoice_date,
            'purchase_invoice_id'   => $batch?->source_invoice_id !== null ? (int) $batch->source_invoice_id : null,
            'purchase_invoice_code' => $batch?->sourceInvoice?->code,
            'purchase_date'         => $batch?->received_at,
            'batch_id'              => (int) $item->inventory_batch_id,
            'quantity'              => $quantity,
            'unit_price'            => $unitPrice,
            'total_sale'            => round($quantity * $unitPrice, 2),
        ];
    }

    private function normalizeExportFilters(array $filters): array
    {
        $clean = [];

        if (!empty($filters['search'])) {
            $clean['search'] = (string) $filters['search'];
        }

        if (!empty($filters['customer_id'])) {
            $clean['customer_id'] = (int) $filters['customer_id'];
        }

        if (is_array($filters['store_ids'] ?? null) && count($filters['store_ids']) > 0) {
            $clean['store_ids'] = array_values(array_map('intval', $filters['store_ids']));
        }

        if (!empty($filters['tag_id'])) {
            $clean['tag_id'] = (int) $filters['tag_id'];
        }
        if (!empty($filters['tag_value_id'])) {
            $clean['tag_value_id'] = (int) $filters['tag_value_id'];
        }

        if (isset($filters['min_quantity']) && is_numeric($filters['min_quantity'])) {
            $clean['min_quantity'] = (float) $filters['min_quantity'];
        }
        if (isset($filters['max_quantity']) && is_numeric($filters['max_quantity'])) {
            $clean['max_quantity'] = (float) $filters['max_quantity'];
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
                fn ($column) => in_array($column, SaleReportExport::COLUMN_KEYS, true),
            ));
            if ($columns !== []) {
                $clean['columns'] = $columns;
            }
        }

        return $clean;
    }
}
