<?php

namespace App\Jobs\Exports;

use App\Enums\AuditAction;
use App\Enums\AuditObjectType;
use App\Exports\AuditLogExport;
use App\Exports\BaseExport;
use App\Models\Business;
use App\Models\Export;
use App\Models\Store;
use App\Models\User;
use App\Repositories\AuditLogRepository;
use App\Services\AuditLogService;
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
            ? "Audit log of business {$scopeName}"
            : "Audit log of store {$scopeName}";

        $metaLines = [
            'Exported by '.$user->name.' ('.$user->email.')',
            'Date exported: '.now()->format('Y-m-d H:i:s'),
        ];

        return new AuditLogExport($query, $title, $metaLines, $isBusinessView);
    }

    protected function filename(Export $export): string
    {
        $metadata = $export->metadata ?? [];
        $scopeName = $metadata['scope_name'] ?? 'unknown';
        $slug = BaseExport::slugForFilename((string) $scopeName);
        $date = now()->format('Y-m-d');

        return "audit-log-{$slug}-{$date}.xlsx";
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

        $auditService = app(AuditLogService::class);

        if ($isBusinessView) {
            $business = Business::find($scopeId);
            $auditService->log(
                null,
                $user,
                AuditObjectType::BUSINESS,
                AuditAction::EXPORTED,
                "{$user->name}({$user->email}) has EXPORTED audit log of business {$scopeName}.",
                [
                    'business_id' => $scopeId,
                    'business_name' => $scopeName,
                    'export_id' => $export->id,
                    'filename' => $export->filename,
                    'filters' => $metadata['filters'] ?? null,
                ],
                $business?->id
            );
        } else {
            $store = Store::find($scopeId);
            $auditService->log(
                $scopeId,
                $user,
                AuditObjectType::STORE,
                AuditAction::EXPORTED,
                "{$user->name}({$user->email}) has EXPORTED audit log of store {$scopeName}.",
                [
                    'store_id' => $scopeId,
                    'store_name' => $scopeName,
                    'business_id' => $store?->business_id,
                    'export_id' => $export->id,
                    'filename' => $export->filename,
                    'filters' => $metadata['filters'] ?? null,
                ],
                $store?->business_id
            );
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
            $query = $repository->businessExportQuery(
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
            $query = $repository->storeExportQuery(
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
