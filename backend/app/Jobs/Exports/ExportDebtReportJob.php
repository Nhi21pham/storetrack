<?php

namespace App\Jobs\Exports;

use App\Enums\InvoiceTypeEnum;
use App\Exports\BaseExport;
use App\Exports\Contracts\Exportable;
use App\Exports\Report\DebtReportExport;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Export;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Repositories\Report\DebtReportRepository;
use App\Services\AuditLog\Loggers\ReportAuditLogger;

/**
 * Generates a debt-report Excel export (customer/receivable or supplier/payable,
 * for a single store or a whole business). The Export record's type identifies
 * the ledger + scope; the query is rebuilt here from metadata to avoid
 * serializing the whole builder.
 */
class ExportDebtReportJob extends BaseExportJob
{
    public const TYPE_RECEIVABLES_STORE = 'receivables-report';

    public const TYPE_RECEIVABLES_BUSINESS = 'receivables-report-business';

    public const TYPE_PAYABLES_STORE = 'payables-report';

    public const TYPE_PAYABLES_BUSINESS = 'payables-report-business';

    protected function buildExport(Export $export): Exportable
    {
        $user = User::find($export->user_id);
        if (! $user) {
            throw new \RuntimeException('Export requesting user no longer exists.');
        }

        $metadata = $export->metadata ?? [];
        $scopeId = (int) ($metadata['scope_id'] ?? 0);
        $filters = $metadata['filters'] ?? [];
        $ledger = $this->ledger($export->type);
        $isBusinessView = $this->isBusinessView($export->type);

        $repository = app(DebtReportRepository::class);

        if ($isBusinessView) {
            $scopeName = $metadata['scope_name'] ?? (Business::find($scopeId)?->name ?? '');
            $title = "{$ledger['title_label']} debt report of business ".$scopeName;
            $parties = $repository->listQueryForBusiness($ledger['model'], $ledger['type'], $scopeId, $filters)->get();
        } else {
            $scopeName = $metadata['scope_name'] ?? (Store::find($scopeId)?->name ?? '');
            $title = "{$ledger['title_label']} debt report of store ".$scopeName;
            $parties = $repository->listQueryForStore($ledger['model'], $ledger['type'], $scopeId, $filters)->get();
        }

        $metaLines = [
            'Exported by '.$user->name.' ('.$user->email.')',
            'Date exported: '.now()->format('Y-m-d H:i:s'),
        ];

        $columns = isset($filters['columns']) && is_array($filters['columns']) ? $filters['columns'] : null;

        return new DebtReportExport(
            $parties,
            $title,
            $metaLines,
            $ledger['party_label'],
            $ledger['spent_label'],
            $filters['start_date'] ?? null,
            $filters['end_date'] ?? null,
            $columns,
            $isBusinessView,
        );
    }

    protected function filename(Export $export): string
    {
        $metadata = $export->metadata ?? [];
        $scopeName = $metadata['scope_name'] ?? 'unknown';

        $slug = BaseExport::slugForFilename((string) $scopeName);
        $datetime = now()->format('YmdHis');
        $prefix = $this->isReceivables($export->type) ? 'customer-debt-report' : 'supplier-debt-report';

        return "{$prefix}-{$slug}-{$datetime}.xlsx";
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
        $label = $this->isReceivables($export->type) ? 'receivable' : 'payable';

        if ($this->isBusinessView($export->type)) {
            app(ReportAuditLogger::class)->reportExportedForBusiness($user, $scopeId, $scopeName, $export, $label);
        } else {
            app(ReportAuditLogger::class)->reportExported($user, $scopeId, $export, $scopeName, $label);
        }
    }

    private function isReceivables(string $type): bool
    {
        return in_array($type, [self::TYPE_RECEIVABLES_STORE, self::TYPE_RECEIVABLES_BUSINESS], true);
    }

    private function isBusinessView(string $type): bool
    {
        return in_array($type, [self::TYPE_RECEIVABLES_BUSINESS, self::TYPE_PAYABLES_BUSINESS], true);
    }

    /** @return array{model: class-string, type: InvoiceTypeEnum, party_label: string, title_label: string, spent_label: string} */
    private function ledger(string $type): array
    {
        if ($this->isReceivables($type)) {
            return [
                'model'       => Customer::class,
                'type'        => InvoiceTypeEnum::SALE,
                'party_label' => 'Customer',
                'title_label' => 'Customer',
                'spent_label' => 'Total Spent',
            ];
        }

        return [
            'model'       => Supplier::class,
            'type'        => InvoiceTypeEnum::PURCHASE,
            'party_label' => 'Supplier',
            'title_label' => 'Supplier',
            'spent_label' => 'Total Purchased',
        ];
    }
}
