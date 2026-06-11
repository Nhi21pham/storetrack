<?php

namespace App\Jobs\Exports;

use App\Exports\BaseExport;
use App\Exports\StockReportExport;
use App\Models\Export;
use App\Models\Store;
use App\Models\User;
use App\Repositories\Report\StockReportRepository;
use App\Services\AuditLog\Loggers\ReportAuditLogger;

class ExportStockReportJob extends BaseExportJob
{
    public const TYPE = 'stock-report';

    protected function buildExport(Export $export): BaseExport
    {
        $user = User::find($export->user_id);
        if (! $user) {
            throw new \RuntimeException('Export requesting user no longer exists.');
        }

        $metadata = $export->metadata ?? [];
        $storeId = (int) ($metadata['scope_id'] ?? 0);
        $filters = $metadata['filters'] ?? [];

        $storeName = $metadata['scope_name'] ?? (Store::find($storeId)?->name ?? '');
        $title = 'Stock report of store '.$storeName;

        $metaLines = [
            'Exported by '.$user->name.' ('.$user->email.')',
            'Date exported: '.now()->format('Y-m-d H:i:s'),
        ];

        $columns = isset($filters['columns']) && is_array($filters['columns']) ? $filters['columns'] : null;
        $query = app(StockReportRepository::class)->listQuery($storeId, $filters);

        return new StockReportExport($query, $title, $metaLines, $columns);
    }

    protected function filename(Export $export): string
    {
        $metadata = $export->metadata ?? [];
        $storeId = (int) ($metadata['scope_id'] ?? 0);

        $name = $storeId
            ? (Store::find($storeId)?->name ?? '')
            : ($metadata['scope_name'] ?? 'unknown');

        $slug = BaseExport::slugForFilename((string) $name);
        $datetime = now()->format('YmdHis');

        return "stock-report-{$slug}-{$datetime}.xlsx";
    }

    protected function onCompleted(Export $export): void
    {
        $user = User::find($export->user_id);
        if (! $user) {
            return;
        }

        $metadata = $export->metadata ?? [];
        $storeId = (int) ($metadata['scope_id'] ?? 0);

        app(ReportAuditLogger::class)->reportExported(
            $user,
            $storeId,
            $export,
            $metadata['scope_name'] ?? '',
            'stock',
        );
    }
}
