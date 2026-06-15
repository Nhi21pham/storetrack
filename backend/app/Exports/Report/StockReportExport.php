<?php

namespace App\Exports\Report;

use App\Exports\BaseExport;
use App\Models\Invoice\InventoryBatch;
use Illuminate\Database\Eloquent\Builder;

class StockReportExport extends BaseExport
{
    public const COLUMN_KEYS = [
        'product_name', 'product_code', 'tags', 'supplier_name', 'invoice_code',
        'purchase_date', 'quantity_received', 'quantity_remaining', 'unit_cost', 'total_cost',
    ];

    /** Running totals accumulated during map(), emitted in the summary row. */
    private float $totalPurchased = 0;
    private float $totalRemaining = 0;
    private float $totalCost = 0;

    /**
     * @param  Builder  $query  filtered, ordered query of InventoryBatch
     * @param  string  $title  e.g. "Stock report of store My Store"
     * @param  string[]  $metaLines
     * @param  string[]|null  $columns  selected column keys; null exports every column
     * @param  bool  $includeStore  prepend a Store column (consolidated business export)
     */
    public function __construct(
        private Builder $query,
        private string $title,
        private array $metaLines,
        private ?array $columns = null,
        private bool $includeStore = false,
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
        $this->totalPurchased += (float) $row->quantity_received;
        $this->totalRemaining += (float) $row->quantity_remaining;
        $this->totalCost += round((float) $row->quantity_received * (float) $row->unit_cost, 2);

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

    /** The Store column is prepended for consolidated business exports, ahead of the selectable columns. */
    private function storeColumn(): array
    {
        return ['heading' => 'Store', 'width' => 24, 'value' => fn (InventoryBatch $row) => (string) ($row->store?->name ?? '')];
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
            'quantity_received'  => ['heading' => 'Purchased',        'width' => 14, 'value' => fn (InventoryBatch $row) => (float) $row->quantity_received,                                  'total' => fn () => $this->totalPurchased],
            'quantity_remaining' => ['heading' => 'In Stock',        'width' => 14, 'value' => fn (InventoryBatch $row) => (float) $row->quantity_remaining,                                 'total' => fn () => $this->totalRemaining],
            'unit_cost'          => ['heading' => 'Cost / Unit',      'width' => 16, 'value' => fn (InventoryBatch $row) => (float) $row->unit_cost],
            'total_cost'         => ['heading' => 'Total Cost',       'width' => 18, 'value' => fn (InventoryBatch $row) => round((float) $row->quantity_received * (float) $row->unit_cost, 2), 'total' => fn () => round($this->totalCost, 2)],
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

    protected function totalsRow(): array
    {
        $row = array_map(
            fn ($column) => isset($column['total']) ? ($column['total'])() : '',
            $this->activeColumns(),
        );

        if ($row !== [] && ($row[0] === '' || $row[0] === null)) {
            $row[0] = 'TOTAL';
        }

        return $row;
    }

    private function activeColumns(): array
    {
        $definitions = $this->columnDefinitions();

        if (empty($this->columns)) {
            $selected = array_values($definitions);
        } else {
            $selected = [];
            foreach ($definitions as $key => $definition) {
                if (in_array($key, $this->columns, true)) {
                    $selected[] = $definition;
                }
            }
            if ($selected === []) {
                $selected = array_values($definitions);
            }
        }

        if ($this->includeStore) {
            return array_merge([$this->storeColumn()], $selected);
        }

        return $selected;
    }
}
