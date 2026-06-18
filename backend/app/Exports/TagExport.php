<?php

namespace App\Exports;

use App\Models\Tag\Tag;
use Illuminate\Database\Eloquent\Builder;

class TagExport extends BaseExport
{
    public const COLUMN_KEYS = ['id', 'name', 'values', 'description', 'created_at', 'updated_at'];

    /**
     * @param  Builder  $query  filtered, ordered query of Tag
     * @param  string  $title  e.g. "Tags of store My Store"
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
     * @param  Tag  $row
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
            'id'          => ['heading' => 'ID',          'width' => 8,  'value' => fn ($row) => (int) $row->id],
            'name'        => ['heading' => 'Key',         'width' => 22, 'value' => fn ($row) => (string) $row->name],
            'values'      => ['heading' => 'Values',      'width' => 34, 'value' => fn ($row) => $row->values->pluck('value')->implode(', ')],
            'description' => ['heading' => 'Description', 'width' => 34, 'value' => fn ($row) => (string) ($row->description ?? '')],
            'created_at'  => ['heading' => 'Created',     'width' => 20, 'value' => fn ($row) => optional($row->created_at)->format('Y-m-d H:i') ?? ''],
            'updated_at'  => ['heading' => 'Updated',     'width' => 20, 'value' => fn ($row) => optional($row->updated_at)->format('Y-m-d H:i') ?? ''],
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
