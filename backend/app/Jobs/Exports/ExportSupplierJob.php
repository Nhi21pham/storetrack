<?php

namespace App\Jobs\Exports;

use App\Exports\BaseExport;
use App\Exports\SupplierExport;
use App\Models\Business;
use App\Models\Export;
use App\Models\Store;
use App\Models\User;
use App\Repositories\SupplierRepository;
use App\Services\AuditLog\Loggers\SupplierAuditLogger;

class ExportSupplierJob extends BaseExportJob
{
    public const TYPE = 'suppliers';

    protected function buildExport(Export $export): BaseExport
    {
        [$user, $title, $query] = $this->resolveScope($export);

        $metaLines = [
            __('exports.exported_by', ['name' => $user->name, 'email' => $user->email]),
            __('exports.date_exported', ['date' => now()->format('Y-m-d H:i:s')]),
        ];

        $filters = $export->metadata['filters'] ?? [];
        $columns = isset($filters['columns']) && is_array($filters['columns']) ? $filters['columns'] : null;

        return new SupplierExport($query, $title, $metaLines, $columns);
    }

    protected function filename(Export $export): string
    {
        $metadata = $export->metadata ?? [];
        $filters = $metadata['filters'] ?? [];
        $storeId = isset($filters['store_id']) ? (int) $filters['store_id'] : null;

        $name = $storeId
            ? (Store::find($storeId)?->name ?? '')
            : ($metadata['scope_name'] ?? 'unknown');

        $slug = BaseExport::slugForFilename((string) $name);
        $datetime = now()->format('YmdHis');

        return "suppliers-{$slug}-{$datetime}.xlsx";
    }

    protected function onCompleted(Export $export): void
    {
        $user = User::find($export->user_id);
        if (! $user) {
            return;
        }

        $metadata = $export->metadata ?? [];
        $businessId = (int) ($metadata['scope_id'] ?? 0);
        $filters = $metadata['filters'] ?? [];
        $storeId = isset($filters['store_id']) ? (int) $filters['store_id'] : null;

        app(SupplierAuditLogger::class)->supplierExported(
            $user,
            $businessId,
            $storeId,
            $export,
            $metadata['scope_name'] ?? '',
        );
    }

    /**
     * @return array{0: User, 1: string, 2: \Illuminate\Database\Eloquent\Builder}
     */
    private function resolveScope(Export $export): array
    {
        $user = User::find($export->user_id);
        if (! $user) {
            throw new \RuntimeException('Export requesting user no longer exists.');
        }

        $metadata = $export->metadata ?? [];
        $businessId = (int) ($metadata['scope_id'] ?? 0);
        $filters = $metadata['filters'] ?? [];
        $storeId = isset($filters['store_id']) ? (int) $filters['store_id'] : null;
        $search = $filters['search'] ?? null;
        $ids = isset($filters['ids']) && is_array($filters['ids']) ? $filters['ids'] : null;

        $business = Business::find($businessId);
        $businessName = $metadata['scope_name'] ?? ($business?->name ?? '');

        $title = $storeId
            ? __('exports.of_store', ['label' => __('exports.label_suppliers'), 'name' => Store::find($storeId)?->name ?? ''])
            : __('exports.of_business', ['label' => __('exports.label_suppliers'), 'name' => $businessName]);

        $query = app(SupplierRepository::class)->listQuery($businessId, $storeId, $search, $ids);

        return [$user, $title, $query];
    }
}
