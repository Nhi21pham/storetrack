<?php

namespace App\Jobs\Exports;

use App\Exports\BaseExport;
use App\Exports\TagExport;
use App\Models\Export;
use App\Models\Store;
use App\Models\User;
use App\Repositories\Tag\TagRepository;
use App\Services\AuditLog\Loggers\TagAuditLogger;

class ExportTagJob extends BaseExportJob
{
    public const TYPE = 'tags';

    protected function buildExport(Export $export): BaseExport
    {
        [$user, $title, $query] = $this->resolveScope($export);

        $metaLines = [
            __('exports.exported_by', ['name' => $user->name, 'email' => $user->email]),
            __('exports.date_exported', ['date' => now()->format('Y-m-d H:i:s')]),
        ];

        $filters = $export->metadata['filters'] ?? [];
        $columns = isset($filters['columns']) && is_array($filters['columns']) ? $filters['columns'] : null;

        return new TagExport($query, $title, $metaLines, $columns);
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

        return "tags-{$slug}-{$datetime}.xlsx";
    }

    protected function onCompleted(Export $export): void
    {
        $user = User::find($export->user_id);
        if (! $user) {
            return;
        }

        $metadata = $export->metadata ?? [];
        $storeId = (int) ($metadata['scope_id'] ?? 0);

        app(TagAuditLogger::class)->tagExported(
            $user,
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
        $storeId = (int) ($metadata['scope_id'] ?? 0);
        $filters = $metadata['filters'] ?? [];
        $search = $filters['search'] ?? null;
        $ids = isset($filters['ids']) && is_array($filters['ids']) ? $filters['ids'] : null;

        $storeName = $metadata['scope_name'] ?? (Store::find($storeId)?->name ?? '');
        $title = __('exports.of_store', ['label' => __('exports.label_tags'), 'name' => $storeName]);

        $query = app(TagRepository::class)->listQuery($storeId, $search, $ids);

        return [$user, $title, $query];
    }
}
