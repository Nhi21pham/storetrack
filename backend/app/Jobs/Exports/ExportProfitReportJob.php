<?php

namespace App\Jobs\Exports;

use App\Exports\BaseExport;
use App\Exports\Report\ProfitReportExport;
use App\Models\Business;
use App\Models\Export;
use App\Models\Store;
use App\Models\User;
use App\Repositories\Report\ProfitReportRepository;
use App\Services\AuditLog\Loggers\ReportAuditLogger;

/**
 * Generates a profit-report Excel export. The Export record carries the scope
 * (a single store, or every store in a business) and filter context in
 * metadata; the query is rebuilt here to avoid serializing the whole builder.
 */
class ExportProfitReportJob extends BaseExportJob
{
    public const TYPE_STORE = 'profit-report';

    public const TYPE_BUSINESS = 'profit-report-business';

    protected function buildExport(Export $export): BaseExport
    {
        $user = User::find($export->user_id);
        if (! $user) {
            throw new \RuntimeException('Export requesting user no longer exists.');
        }

        $metadata = $export->metadata ?? [];
        $scopeId = (int) ($metadata['scope_id'] ?? 0);
        $filters = $metadata['filters'] ?? [];
        $isBusinessView = ($export->type === self::TYPE_BUSINESS);

        $repository = app(ProfitReportRepository::class);

        if ($isBusinessView) {
            $scopeName = $metadata['scope_name'] ?? (Business::find($scopeId)?->name ?? '');
            $title = __('exports.of_business', ['label' => __('exports.label_profit_report'), 'name' => $scopeName]);
            $query = $repository->listQueryForBusiness($scopeId, $filters);
        } else {
            $scopeName = $metadata['scope_name'] ?? (Store::find($scopeId)?->name ?? '');
            $title = __('exports.of_store', ['label' => __('exports.label_profit_report'), 'name' => $scopeName]);
            $query = $repository->listQuery($scopeId, $filters);
        }

        $metaLines = [
            __('exports.exported_by', ['name' => $user->name, 'email' => $user->email]),
            __('exports.date_exported', ['date' => now()->format('Y-m-d H:i:s')]),
        ];

        $columns = isset($filters['columns']) && is_array($filters['columns']) ? $filters['columns'] : null;

        return new ProfitReportExport($query, $title, $metaLines, $columns, $isBusinessView);
    }

    protected function filename(Export $export): string
    {
        $metadata = $export->metadata ?? [];
        $scopeName = $metadata['scope_name'] ?? 'unknown';

        $slug = BaseExport::slugForFilename((string) $scopeName);
        $datetime = now()->format('YmdHis');

        return "profit-report-{$slug}-{$datetime}.xlsx";
    }

    protected function onCompleted(Export $export): void
    {
        $user = User::find($export->user_id);
        if (! $user) {
            return;
        }

        $metadata = $export->metadata ?? [];
        $scopeId = (int) ($metadata['scope_id'] ?? 0);
        $scopeName = (string) ($metadata['scope_name'] ?? '');

        if ($export->type === self::TYPE_BUSINESS) {
            app(ReportAuditLogger::class)->reportExportedForBusiness($user, $scopeId, $scopeName, $export, 'profit');
        } else {
            app(ReportAuditLogger::class)->reportExported($user, $scopeId, $export, $scopeName, 'profit');
        }
    }
}
