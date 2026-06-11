<?php

namespace App\Exports;

use App\Models\Invoice\InventoryBatch;
use Illuminate\Database\Eloquent\Builder;

class StockReportExport extends BaseExport
{
    public const COLUMN_KEYS = [
        'product_name', 'product_code', 'tags', 'supplier_name', 'invoice_code',
        'purchase_date', 'quantity_received', 'quantity_remaining', 'unit_cost', 'total_cost',
    ];

    /**
     * @param  Builder  $query  filtered, ordered query of InventoryBatch
     * @param  string  $title  e.g. "Stock report of store My Store"
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
     * @param  InventoryBatch  $row
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
            'product_name'       => ['heading' => 'Product',          'width' => 28, 'value' => fn (InventoryBatch $row) => (string) ($row->product?->name ?? '')],
            'product_code'       => ['heading' => 'Code',             'width' => 16, 'value' => fn (InventoryBatch $row) => (string) ($row->product?->code ?? '')],
            'tags'               => ['heading' => 'Tags',             'width' => 30, 'value' => fn (InventoryBatch $row) => $this->formatTags($row)],
            'supplier_name'      => ['heading' => 'Supplier',         'width' => 26, 'value' => fn (InventoryBatch $row) => (string) ($row->sourceInvoice?->party_name ?? '')],
            'invoice_code'       => ['heading' => 'Purchase Invoice', 'width' => 18, 'value' => fn (InventoryBatch $row) => (string) ($row->sourceInvoice?->code ?? '')],
            'purchase_date'      => ['heading' => 'Purchase Date',    'width' => 16, 'value' => fn (InventoryBatch $row) => optional($row->received_at)->format('Y-m-d') ?? ''],
            'quantity_received'  => ['heading' => 'Purchased',        'width' => 14, 'value' => fn (InventoryBatch $row) => (float) $row->quantity_received],
            'quantity_remaining' => ['heading' => 'In Stock',        'width' => 14, 'value' => fn (InventoryBatch $row) => (float) $row->quantity_remaining],
            'unit_cost'          => ['heading' => 'Cost / Unit',      'width' => 16, 'value' => fn (InventoryBatch $row) => (float) $row->unit_cost],
            'total_cost'         => ['heading' => 'Total Cost',       'width' => 18, 'value' => fn (InventoryBatch $row) => round((float) $row->quantity_received * (float) $row->unit_cost, 2)],
        ];
    }

    private function formatTags(InventoryBatch $row): string
    {
        $tags = $row->product?->tags ?? [];

        return collect($tags)
            ->map(fn ($tag) => $tag['value'] !== null ? "{$tag['tag_name']}: {$tag['value']}" : $tag['tag_name'])
            ->filter()
            ->implode(', ');
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
