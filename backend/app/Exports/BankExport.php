<?php

namespace App\Exports;

use App\Models\Bank;
use Illuminate\Database\Eloquent\Builder;

class BankExport extends BaseExport
{
    public const COLUMN_KEYS = ['short_name', 'full_name_vi', 'full_name_en', 'status'];

    /**
     * @param  Builder  $query  filtered, ordered query of Bank
     * @param  string  $title  e.g. "Banks of business My Business"
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
     * @param  Bank  $row
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
            'short_name'   => ['heading' => 'Short Name',      'width' => 18, 'value' => fn ($row) => (string) $row->short_name],
            'full_name_vi' => ['heading' => 'Vietnamese Name', 'width' => 34, 'value' => fn ($row) => (string) ($row->full_name_vi ?? '')],
            'full_name_en' => ['heading' => 'English Name',    'width' => 34, 'value' => fn ($row) => (string) ($row->full_name_en ?? '')],
            'status'       => ['heading' => 'Status',          'width' => 12, 'value' => fn ($row) => $row->is_active ? 'Active' : 'Inactive'],
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
