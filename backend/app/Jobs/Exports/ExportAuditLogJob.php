<?php

namespace App\Jobs\Exports;

use App\Exports\AuditLogExport;
use App\Exports\BaseExport;
use App\Models\Business;
use App\Models\Export;
use App\Models\Store;
use App\Models\User;
use App\Repositories\AuditLogRepository;
use App\Services\AuditLog\Loggers\BusinessAuditLogger;
use App\Services\AuditLog\Loggers\StoreAuditLogger;
use Illuminate\Database\Eloquent\Builder;

/**
 * Generates an audit-log Excel export. The Export record carries the scope
 * and filter context in metadata; we rebuild the query in the job to avoid
 * serializing the entire builder.
 */
class ExportAuditLogJob extends BaseExportJob
{
    public const TYPE_STORE = 'audit_log_store';

    public const TYPE_BUSINESS = 'audit_log_business';

    protected function buildExport(Export $export): BaseExport
    {
        [$user, $scopeName, $isBusinessView, $query] = $this->resolveScope($export);

        $title = $isBusinessView
            ? __('exports.of_business', ['label' => __('exports.label_audit'), 'name' => $scopeName])
            : __('exports.of_store', ['label' => __('exports.label_audit'), 'name' => $scopeName]);

        $metaLines = [
            __('exports.exported_by', ['name' => $user->name, 'email' => $user->email]),
            __('exports.date_exported', ['date' => now()->format('Y-m-d H:i:s')]),
        ];

        return new AuditLogExport($query, $title, $metaLines, $isBusinessView);
    }

    protected function filename(Export $export): string
    {
        $metadata = $export->metadata ?? [];
        $scopeName = $metadata['scope_name'] ?? 'unknown';
        $slug = BaseExport::slugForFilename((string) $scopeName);
        $datetime = now()->format('YmdHis');

        return "auditlog-{$slug}-{$datetime}.xlsx";
    }

    protected function onCompleted(Export $export): void
    {
        $user = User::find($export->user_id);
        if (! $user) {
            return;
        }

        $metadata = $export->metadata ?? [];
        $isBusinessView = ($export->type === self::TYPE_BUSINESS);
        $scopeName = $metadata['scope_name'] ?? '';
        $scopeId = (int) ($metadata['scope_id'] ?? 0);

        if ($isBusinessView) {
            app(BusinessAuditLogger::class)->auditLogExported($user, $scopeId, $scopeName, $export);
        } else {
            app(StoreAuditLogger::class)->auditLogExported($user, $scopeId, $scopeName, $export);
        }
    }

    /**
     * @return array{0: User, 1: string, 2: bool, 3: Builder}
     */
    private function resolveScope(Export $export): array
    {
        $user = User::find($export->user_id);
        if (! $user) {
            throw new \RuntimeException('Export requesting user no longer exists.');
        }

        $metadata = $export->metadata ?? [];
        $scopeId = (int) ($metadata['scope_id'] ?? 0);
        $filters = $metadata['filters'] ?? [];
        $isBusinessView = ($export->type === self::TYPE_BUSINESS);

        $repository = app(AuditLogRepository::class);

        if ($isBusinessView) {
            $business = Business::find($scopeId);
            $scopeName = $metadata['scope_name'] ?? ($business?->name ?? '');
            $query = $repository->businessQuery(
                $scopeId,
                $filters['start_date'] ?? null,
                $filters['end_date'] ?? null,
                $filters['object_type'] ?? null,
                $filters['action'] ?? null,
                $filters['store_name'] ?? null,
                $filters['search'] ?? null,
            );
        } else {
            $store = Store::find($scopeId);
            $scopeName = $metadata['scope_name'] ?? ($store?->name ?? '');
            $query = $repository->storeQuery(
                $scopeId,
                $filters['start_date'] ?? null,
                $filters['end_date'] ?? null,
                $filters['object_type'] ?? null,
                $filters['action'] ?? null,
                $filters['search'] ?? null,
            );
        }

        return [$user, (string) $scopeName, $isBusinessView, $query];
    }
}
