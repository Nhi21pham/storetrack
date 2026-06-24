<?php

namespace App\Exports;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;

class UnitExport extends BaseExport
{
    public const COLUMN_KEYS = ['id', 'name', 'status', 'created_at', 'updated_at'];

    /**
     * @param  Builder  $query  filtered, ordered query of Unit
     * @param  string  $title  e.g. "Units of store My Store"
     * @param  string[]  $metaLines
     * @param  string[]|null  $columns  selected column keys; null exports every column
     */
    public function __construct(
        private Builder $query,
        private string $title,
        private array $metaLines,
        private ?array $columns = null,
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
        return array_map(fn ($column) => $column['heading'], $this->activeColumns());
    }

    public function exportQuery(): Builder
    {
        return $this->query;
    }

    /**
     * @param  Unit  $row
     */
    public function map($row): array
    {
        return array_map(fn ($column) => $column['value']($row), $this->activeColumns());
    }

    public function columnWidths(): array
    {
        $widths = [];
        foreach (array_values($this->activeColumns()) as $index => $column) {
            $widths[$index + 1] = $column['width'];
        }

        return $widths;
    }

    private function columnDefinitions(): array
    {
        return [
            'id'         => ['heading' => __('exports.col_id'),        'width' => 8,  'value' => fn ($row) => (int) $row->id],
            'name'       => ['heading' => __('exports.col_unit_name'), 'width' => 26, 'value' => fn ($row) => (string) $row->name],
            'status'     => ['heading' => __('exports.col_status'),    'width' => 14, 'value' => fn ($row) => $row->is_active ? __('exports.status_active') : __('exports.status_inactive')],
            'created_at' => ['heading' => __('exports.col_created'),   'width' => 20, 'value' => fn ($row) => optional($row->created_at)->format('Y-m-d H:i') ?? ''],
            'updated_at' => ['heading' => __('exports.col_updated'),   'width' => 20, 'value' => fn ($row) => optional($row->updated_at)->format('Y-m-d H:i') ?? ''],
        ];
    }

    private function activeColumns(): array
    {
        $definitions = $this->columnDefinitions();

        if (empty($this->columns)) {
            return array_values($definitions);
        }

        $selected = [];
        foreach ($definitions as $key => $definition) {
            if (in_array($key, $this->columns, true)) {
                $selected[] = $definition;
            }
        }

        return $selected !== [] ? $selected : array_values($definitions);
    }
}
