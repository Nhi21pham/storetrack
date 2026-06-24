<?php

namespace App\Jobs\Exports;

use App\Enums\InvoiceTypeEnum;
use App\Exports\BaseExport;
use App\Exports\InvoiceExport;
use App\Models\Export;
use App\Models\Store;
use App\Models\User;
use App\Repositories\Invoice\InvoiceRepository;
use App\Services\AuditLog\Loggers\InvoiceAuditLogger;

class ExportInvoiceJob extends BaseExportJob
{
    public const TYPE = 'invoices';

    protected function buildExport(Export $export): BaseExport
    {
        [$user, $title, $partyLabel, $query] = $this->resolveScope($export);

        $metaLines = [
            __('exports.exported_by', ['name' => $user->name, 'email' => $user->email]),
            __('exports.date_exported', ['date' => now()->format('Y-m-d H:i:s')]),
        ];

        $filters = $export->metadata['filters'] ?? [];
        $columns = isset($filters['columns']) && is_array($filters['columns']) ? $filters['columns'] : null;

        return new InvoiceExport($query, $title, $metaLines, $partyLabel, $columns);
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
        $prefix = $this->typePrefix($metadata['filters']['type'] ?? null);

        return "{$prefix}-{$slug}-{$datetime}.xlsx";
    }

    protected function onCompleted(Export $export): void
    {
        $user = User::find($export->user_id);
        if (! $user) {
            return;
        }

        $metadata = $export->metadata ?? [];
        $storeId = (int) ($metadata['scope_id'] ?? 0);

        app(InvoiceAuditLogger::class)->invoiceExported(
            $user,
            $storeId,
            $export,
            $metadata['scope_name'] ?? '',
        );
    }

    /**
     * @return array{0: User, 1: string, 2: string, 3: \Illuminate\Database\Eloquent\Builder}
     */
    private function resolveScope(Export $export): array
    {
        $user = User::find($export->user_id);
        if (! $user) {
            throw new \RuntimeException('Export requesting user no longer exists.');
        }

        $metadata = $export->metadata ?? [];
        $storeId = (int) ($metadata['scope_id'] ?? 0);
        $filters = $metadata['filters'] ?? [];

        $storeName = $metadata['scope_name'] ?? (Store::find($storeId)?->name ?? '');
        $type = $this->resolveType($filters['type'] ?? null);

        $title = __('exports.of_store', ['label' => $this->titlePrefix($type), 'name' => $storeName]);
        $partyLabel = $this->partyLabel($type);

        $query = app(InvoiceRepository::class)->listQuery($storeId, $filters);

        return [$user, $title, $partyLabel, $query];
    }

    private function resolveType(?string $type): ?InvoiceTypeEnum
    {
        return $type !== null ? InvoiceTypeEnum::tryFrom($type) : null;
    }

    private function titlePrefix(?InvoiceTypeEnum $type): string
    {
        return match ($type) {
            InvoiceTypeEnum::PURCHASE => __('exports.label_purchase_invoices'),
            InvoiceTypeEnum::SALE     => __('exports.label_sale_invoices'),
            default                   => __('exports.label_invoices'),
        };
    }

    private function partyLabel(?InvoiceTypeEnum $type): string
    {
        return match ($type) {
            InvoiceTypeEnum::PURCHASE => __('exports.col_supplier'),
            InvoiceTypeEnum::SALE     => __('exports.col_customer'),
            default                   => __('exports.col_customer'),
        };
    }

    private function typePrefix(?string $type): string
    {
        return match ($this->resolveType($type)) {
            InvoiceTypeEnum::PURCHASE => 'purchase-invoices',
            InvoiceTypeEnum::SALE     => 'sale-invoices',
            default                   => 'invoices',
        };
    }
}
