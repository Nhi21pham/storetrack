<?php

namespace App\Exports;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class AuditLogExport extends BaseExport
{
    /**
     * @param  Builder  $query  filtered, ordered query of AuditLog
     *                          (business view callers should ->with('store') already)
     * @param  string  $title  e.g. "Audit log of My Store"
     * @param  string[]  $metaLines  e.g. ["Exported by John (john@x.com)", "Date exported: 2026-04-29 12:34:56"]
     * @param  bool  $includeStoreColumn  when true, prepends the "Store Name" column (business view)
     */
    public function __construct(
        private Builder $query,
        private string $title,
        private array $metaLines,
        private bool $includeStoreColumn = false,
    ) {}

    public function title(): string
    {
        return $this->title;
    }

    public function metaLines(): array
    {
        return $this->metaLines;
    }

    public function columnHeadings(): array
    {
        $columns = [];
        if ($this->includeStoreColumn) {
            $columns[] = __('exports.col_store_name');
        }
        $columns[] = __('exports.col_object');
        $columns[] = __('exports.col_actor_name');
        $columns[] = __('exports.col_actor_email');
        $columns[] = __('exports.col_action');
        $columns[] = __('exports.col_message');
        $columns[] = __('exports.col_datetime');

        return $columns;
    }

    public function exportQuery(): Builder
    {
        return $this->query;
    }

    /**
     * @param  AuditLog  $row
     */
    public function map($row): array
    {
        $cells = [];
        if ($this->includeStoreColumn) {
            $cells[] = $row->store_name ?? '';
        }
        $cells[] = Str::headline((string) $row->object_type);
        $cells[] = $row->actor_name ?? '';
        $cells[] = $row->actor_email ?? '';
        $cells[] = Str::headline((string) $row->action);
        $cells[] = (string) $row->message;
        $cells[] = optional($row->created_at)->format('Y-m-d H:i:s') ?? '';

        return $cells;
    }

    public function columnWidths(): array
    {
        if ($this->includeStoreColumn) {
            return [1 => 22, 2 => 14, 3 => 22, 4 => 28, 5 => 14, 6 => 60, 7 => 20];
        }

        return [1 => 14, 2 => 22, 3 => 28, 4 => 14, 5 => 60, 6 => 20];
    }
}
