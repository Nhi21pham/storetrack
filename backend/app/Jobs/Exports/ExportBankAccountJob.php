<?php

namespace App\Jobs\Exports;

use App\Exports\BankAccountExport;
use App\Exports\BaseExport;
use App\Models\Business;
use App\Models\Export;
use App\Models\User;
use App\Repositories\BankAccountRepository;
use App\Services\AuditLog\Loggers\BankAccountAuditLogger;

class ExportBankAccountJob extends BaseExportJob
{
    public const TYPE = 'bank-accounts';

    protected function buildExport(Export $export): BaseExport
    {
        [$user, $title, $query] = $this->resolveScope($export);

        $metaLines = [
            'Exported by '.$user->name.' ('.$user->email.')',
            'Date exported: '.now()->format('Y-m-d H:i:s'),
        ];

        $filters = $export->metadata['filters'] ?? [];
        $columns = isset($filters['columns']) && is_array($filters['columns']) ? $filters['columns'] : null;

        return new BankAccountExport($query, $title, $metaLines, $columns);
    }

    protected function filename(Export $export): string
    {
        $metadata = $export->metadata ?? [];
        $businessId = (int) ($metadata['scope_id'] ?? 0);

        $name = $businessId
            ? (Business::find($businessId)?->name ?? '')
            : ($metadata['scope_name'] ?? 'unknown');

        $slug = BaseExport::slugForFilename((string) $name);
        $datetime = now()->format('YmdHis');

        return "bank-accounts-{$slug}-{$datetime}.xlsx";
    }

    protected function onCompleted(Export $export): void
    {
        $user = User::find($export->user_id);
        if (! $user) {
            return;
        }

        $metadata = $export->metadata ?? [];
        $businessId = (int) ($metadata['scope_id'] ?? 0);

        app(BankAccountAuditLogger::class)->bankAccountExported(
            $user,
            $businessId,
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
        $search = $filters['search'] ?? null;
        $ids = isset($filters['ids']) && is_array($filters['ids']) ? $filters['ids'] : null;

        $businessName = $metadata['scope_name'] ?? (Business::find($businessId)?->name ?? '');
        $title = 'Bank accounts of business '.$businessName;

        $query = app(BankAccountRepository::class)->listQuery($businessId, $search, $ids);

        return [$user, $title, $query];
    }
}
