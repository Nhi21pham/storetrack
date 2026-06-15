<?php

namespace App\Exports\Report;

use App\Exports\BaseExport;
use App\Models\Invoice\InvoiceProduct;
use Illuminate\Database\Eloquent\Builder;

/**
 * Generates a Top Products Excel export: one row per product, ranked by the
 * chosen metric (the query is already ordered), carrying qty sold, revenue,
 * FIFO profit and # orders. The leading No. column is the rank.
 */
class TopProductsReportExport extends BaseExport
{
    public const COLUMN_KEYS = [
        'product_name', 'product_code', 'qty_sold', 'revenue', 'profit', 'orders',
    ];

    /** Running rank assigned to rows in ranked order. */
    private int $counter = 0;

    /** Running totals accumulated during map(), emitted in the summary row. */
    private float $totalQty = 0;
    private float $totalRevenue = 0;
    private float $totalProfit = 0;

    /**
     * @param  Builder  $query  the aggregated, ordered query of products
     * @param  string  $title  e.g. "Top products of store My Store"
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
     * @param  InvoiceProduct  $row
     */
    public function map($row): array
    {
        $this->totalQty += (float) $row->qty_sold;
        $this->totalRevenue += round((float) $row->revenue, 2);
        $this->totalProfit += $this->profit($row);

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

    /** The rank (No.) column is always first, independent of the selectable columns. */
    private function orderNumberColumn(): array
    {
        return ['heading' => 'No.', 'width' => 8, 'value' => fn (InvoiceProduct $row) => ++$this->counter];
    }

    private function columnDefinitions(): array
    {
        return [
            'product_name' => ['heading' => 'Product',   'width' => 28, 'value' => fn (InvoiceProduct $row) => (string) ($row->product_name ?? '')],
            'product_code' => ['heading' => 'Code',      'width' => 16, 'value' => fn (InvoiceProduct $row) => (string) ($row->product_code ?? '')],
            'qty_sold'     => ['heading' => 'Qty Sold',  'width' => 14, 'value' => fn (InvoiceProduct $row) => (float) $row->qty_sold,        'total' => fn () => $this->totalQty],
            'revenue'      => ['heading' => 'Revenue',   'width' => 18, 'value' => fn (InvoiceProduct $row) => round((float) $row->revenue, 2), 'total' => fn () => round($this->totalRevenue, 2)],
            'profit'       => ['heading' => 'Profit',    'width' => 18, 'value' => fn (InvoiceProduct $row) => $this->profit($row),            'total' => fn () => round($this->totalProfit, 2)],
            'orders'       => ['heading' => '# Orders',  'width' => 12, 'value' => fn (InvoiceProduct $row) => (int) $row->orders],
        ];
    }

    private function profit(InvoiceProduct $row): float
    {
        return round((float) $row->revenue - (float) $row->cost, 2);
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

        return array_merge([$this->orderNumberColumn()], $selected);
    }
}
