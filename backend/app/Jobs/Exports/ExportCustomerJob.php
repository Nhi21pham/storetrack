<?php

namespace App\Jobs\Exports;

use App\Exports\BaseExport;
use App\Exports\CustomerExport;
use App\Models\Business;
use App\Models\Export;
use App\Models\Store;
use App\Models\User;
use App\Repositories\CustomerRepository;
use App\Services\AuditLogService;

class ExportCustomerJob extends BaseExportJob
{
    public const TYPE = 'customers';

    protected function buildExport(Export $export): BaseExport
    {
        [$user, $title, $query] = $this->resolveScope($export);

        $metaLines = [
            'Exported by '.$user->name.' ('.$user->email.')',
            'Date exported: '.now()->format('Y-m-d H:i:s'),
        ];

        return new CustomerExport($query, $title, $metaLines);
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

        return "customers-{$slug}-{$datetime}.xlsx";
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

        app(AuditLogService::class)->customerExported(
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
            ? 'Customers of store '.(Store::find($storeId)?->name ?? '')
            : 'Customers of business '.$businessName;

        $query = app(CustomerRepository::class)->listQuery($businessId, $storeId, $search, $ids);

        return [$user, $title, $query];
    }
}
