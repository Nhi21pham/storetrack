<?php

namespace App\Services\Report;

use App\Enums\ExportScope;
use App\Enums\PermissionEnum;
use App\Exports\StockReportExport;
use App\Jobs\Exports\ExportStockReportJob;
use App\Models\Export;
use App\Models\Invoice\InventoryBatch;
use App\Models\Store;
use App\Models\User;
use App\Repositories\Report\StockReportRepository;
use App\Services\ExportService;
use App\Services\PermissionService;

class StockReportService
{
    public function __construct(
        private StockReportRepository $stockReportRepository,
        private PermissionService $permissionService,
        private ExportService $exportService,
    ) {}

    /**
     * The full stock report for a store: one row per purchase lot. The frontend
     * filters/searches/sorts these client-side, mirroring the invoice list.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getReport(User $user, int $storeId): array
    {
        $this->permissionService->authorizeStore($user, PermissionEnum::UPDATE_INVOICE, $storeId);

        return $this->stockReportRepository->all($storeId)
            ->map(fn (InventoryBatch $batch) => $this->toRow($batch))
            ->all();
    }

    public function queueExport(User $user, int $storeId, array $filters = [], ?string $clientId = null): Export
    {
        $this->permissionService->authorizeStore($user, PermissionEnum::UPDATE_INVOICE, $storeId);

        return $this->exportService->queue(
            $user,
            ExportStockReportJob::TYPE,
            ExportScope::STORE,
            $storeId,
            Store::find($storeId)?->name,
            $this->normalizeExportFilters($filters),
            ExportStockReportJob::class,
            $clientId,
        );
    }

    /** Projects a batch into the report row shape exposed by GraphQL. */
    private function toRow(InventoryBatch $batch): array
    {
        $invoice = $batch->sourceInvoice;
        $received = (float) $batch->quantity_received;
        $remaining = (float) $batch->quantity_remaining;
        $unitCost = (float) $batch->unit_cost;

        return [
            'id'                 => (int) $batch->id,
            'product_id'         => (int) $batch->product_id,
            'product_name'       => $batch->product?->name,
            'product_code'       => $batch->product?->code,
            'tags'               => $batch->product?->tags ?? [],
            'supplier_party_id'  => $invoice?->party_id !== null ? (int) $invoice->party_id : null,
            'supplier_name'      => $invoice?->party_name,
            'invoice_id'         => $batch->source_invoice_id !== null ? (int) $batch->source_invoice_id : null,
            'invoice_code'       => $invoice?->code,
            'purchase_date'      => $batch->received_at,
            'quantity_received'  => (float) $batch->quantity_received,
            'quantity_remaining' => $remaining,
            'unit_cost'          => $unitCost,
            'total_cost'         => round($received * $unitCost, 2),
        ];
    }

    private function normalizeExportFilters(array $filters): array
    {
        $clean = [];

        if (!empty($filters['search'])) {
            $clean['search'] = (string) $filters['search'];
        }

        if (!empty($filters['supplier_id'])) {
            $clean['supplier_id'] = (int) $filters['supplier_id'];
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

        if ($this->isTruthy($filters['include_out_of_stock'] ?? null)) {
            $clean['include_out_of_stock'] = true;
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
                fn ($column) => in_array($column, StockReportExport::COLUMN_KEYS, true),
            ));
            if ($columns !== []) {
                $clean['columns'] = $columns;
            }
        }

        return $clean;
    }

    /** Query-string booleans arrive as "1"/"true"/"on"; treat those as true. */
    private function isTruthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true);
    }
}
