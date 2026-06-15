<?php

namespace App\Services\Report;

use App\Enums\ExportScope;
use App\Enums\PermissionEnum;
use App\Exports\Report\TopProductsReportExport;
use App\Jobs\Exports\ExportTopProductsReportJob;
use App\Models\Business;
use App\Models\Export;
use App\Models\Invoice\InvoiceProduct;
use App\Models\Store;
use App\Models\User;
use App\Repositories\Report\TopProductsReportRepository;
use App\Services\ExportService;
use App\Services\PermissionService;

/**
 * The Top Products report: one ranked row per product (qty sold, revenue, FIFO
 * profit, # orders) over a date range. Aggregation lives in the repository; this
 * service authorizes, projects rows for GraphQL, and queues exports.
 */
class TopProductsReportService
{
    public function __construct(
        private TopProductsReportRepository $repository,
        private PermissionService $permissionService,
        private ExportService $exportService,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getReport(User $user, int $storeId, array $filters = []): array
    {
        $this->permissionService->authorizeStore($user, PermissionEnum::UPDATE_INVOICE, $storeId);

        return $this->repository->report($storeId, $filters)
            ->map(fn (InvoiceProduct $row) => $this->toRow($row))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBusinessReport(User $user, int $businessId, array $filters = []): array
    {
        $this->permissionService->authorizeBusinessOwner($user, $businessId);

        return $this->repository->reportForBusiness($businessId, $filters)
            ->map(fn (InvoiceProduct $row) => $this->toRow($row))
            ->all();
    }

    public function queueExport(User $user, int $storeId, array $filters = [], ?string $clientId = null): Export
    {
        $this->permissionService->authorizeStore($user, PermissionEnum::UPDATE_INVOICE, $storeId);

        return $this->exportService->queue(
            $user,
            ExportTopProductsReportJob::TYPE_STORE,
            ExportScope::STORE,
            $storeId,
            Store::find($storeId)?->name,
            $this->normalizeExportFilters($filters),
            ExportTopProductsReportJob::class,
            $clientId,
        );
    }

    public function queueBusinessExport(User $user, int $businessId, array $filters = [], ?string $clientId = null): Export
    {
        $this->permissionService->authorizeBusinessOwner($user, $businessId);

        return $this->exportService->queue(
            $user,
            ExportTopProductsReportJob::TYPE_BUSINESS,
            ExportScope::BUSINESS,
            $businessId,
            Business::find($businessId)?->name,
            $this->normalizeExportFilters($filters),
            ExportTopProductsReportJob::class,
            $clientId,
        );
    }

    /** Projects an aggregated product row into the report row shape exposed by GraphQL. */
    private function toRow(InvoiceProduct $row): array
    {
        $revenue = round((float) $row->revenue, 2);
        $cost = round((float) $row->cost, 2);

        return [
            'product_id'   => (int) $row->product_id,
            'product_name' => $row->product_name,
            'product_code' => $row->product_code,
            'tags'         => $row->tags ?? [],
            'qty_sold'     => (float) $row->qty_sold,
            'revenue'      => $revenue,
            'profit'       => round($revenue - $cost, 2),
            'orders'       => (int) $row->orders,
        ];
    }

    private function normalizeExportFilters(array $filters): array
    {
        $clean = [];

        if (!empty($filters['search'])) {
            $clean['search'] = (string) $filters['search'];
        }
        if (!empty($filters['tag_id'])) {
            $clean['tag_id'] = (int) $filters['tag_id'];
        }
        if (!empty($filters['tag_value_id'])) {
            $clean['tag_value_id'] = (int) $filters['tag_value_id'];
        }
        if (!empty($filters['start_date'])) {
            $clean['start_date'] = (string) $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $clean['end_date'] = (string) $filters['end_date'];
        }
        if (in_array($filters['sort'] ?? null, ['qty_sold', 'revenue', 'profit', 'orders'], true)) {
            $clean['sort'] = (string) $filters['sort'];
        }
        if (is_array($filters['store_ids'] ?? null) && count($filters['store_ids']) > 0) {
            $clean['store_ids'] = array_values(array_map('intval', $filters['store_ids']));
        }
        if (is_array($filters['ids'] ?? null) && count($filters['ids']) > 0) {
            $clean['ids'] = array_values(array_map('intval', $filters['ids']));
        }
        if (is_array($filters['columns'] ?? null)) {
            $columns = array_values(array_filter(
                $filters['columns'],
                fn ($column) => in_array($column, TopProductsReportExport::COLUMN_KEYS, true),
            ));
            if ($columns !== []) {
                $clean['columns'] = $columns;
            }
        }

        return $clean;
    }
}
