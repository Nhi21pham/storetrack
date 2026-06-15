<?php

namespace App\Exports\Report;

use App\Exports\BaseExport;
use App\Models\Invoice\InvoiceProductCost;
use Illuminate\Database\Eloquent\Builder;

class ProfitReportExport extends BaseExport
{
    public const COLUMN_KEYS = [
        'product_name', 'product_code', 'tags', 'purchase_invoice_code', 'purchase_date',
        'invoice_code', 'invoice_date', 'quantity', 'unit_cost', 'unit_price',
        'cost', 'revenue', 'profit',
    ];

    /** Running ordinal (STT) assigned to rows in export order. */
    private int $counter = 0;

    /** Running totals accumulated during map(), emitted in the summary row. */
    private float $totalQty = 0;
    private float $totalRevenue = 0;
    private float $totalCost = 0;
    private float $totalProfit = 0;

    /**
     * @param  Builder  $query  filtered, ordered query of InvoiceProductCost
     * @param  string  $title  e.g. "Profit report of store My Store"
     * @param  string[]  $metaLines
     * @param  string[]|null  $columns  selected column keys; null exports every column
     * @param  bool  $includeStore  add a Store column (consolidated business export)
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
     * @param  InvoiceProductCost  $row
     */
    public function map($row): array
    {
        $revenue = $this->revenue($row);
        $cost = $this->cost($row);

        $this->totalQty += (float) $row->quantity;
        $this->totalRevenue += $revenue;
        $this->totalCost += $cost;
        $this->totalProfit += round($revenue - $cost, 2);

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

    /** Revenue for this batch-slice: units sold from the batch x the sale price. */
    private function revenue(InvoiceProductCost $row): float
    {
        return round((float) $row->quantity * (float) ($row->invoiceProduct?->unit_price ?? 0), 2);
    }

    /** Cost (COGS) for this batch-slice: units sold from the batch x the batch's unit cost. */
    private function cost(InvoiceProductCost $row): float
    {
        return round((float) $row->quantity * (float) $row->unit_cost, 2);
    }

    /** The STT column is always first, independent of the selectable columns. */
    private function orderNumberColumn(): array
    {
        return ['heading' => 'No.', 'width' => 8, 'value' => fn (InvoiceProductCost $row) => ++$this->counter];
    }

    /** The Store column is added for consolidated business exports, after the STT column. */
    private function storeColumn(): array
    {
        return ['heading' => 'Store', 'width' => 24, 'value' => fn (InvoiceProductCost $row) => (string) ($row->invoiceProduct?->invoice?->store?->name ?? '')];
    }

    private function columnDefinitions(): array
    {
        return [
            'product_name'          => ['heading' => 'Product',           'width' => 28, 'value' => fn (InvoiceProductCost $row) => (string) ($row->invoiceProduct?->product?->name ?? $row->invoiceProduct?->product_name ?? '')],
            'product_code'          => ['heading' => 'Code',              'width' => 16, 'value' => fn (InvoiceProductCost $row) => (string) ($row->invoiceProduct?->product?->code ?? '')],
            'tags'                  => ['heading' => 'Tags',              'width' => 30, 'value' => fn (InvoiceProductCost $row) => $this->formatTags($row)],
            'purchase_invoice_code' => ['heading' => 'Purchase Invoice',  'width' => 18, 'value' => fn (InvoiceProductCost $row) => (string) ($row->batch?->sourceInvoice?->code ?? '')],
            'purchase_date'         => ['heading' => 'Purchase Date',     'width' => 16, 'value' => fn (InvoiceProductCost $row) => optional($row->batch?->received_at)->format('Y-m-d') ?? ''],
            'invoice_code'          => ['heading' => 'Sale Invoice',      'width' => 18, 'value' => fn (InvoiceProductCost $row) => (string) ($row->invoiceProduct?->invoice?->code ?? '')],
            'invoice_date'          => ['heading' => 'Sale Date',         'width' => 16, 'value' => fn (InvoiceProductCost $row) => optional($row->invoiceProduct?->invoice?->invoice_date)->format('Y-m-d') ?? ''],
            'quantity'              => ['heading' => 'Qty Sold',          'width' => 14, 'value' => fn (InvoiceProductCost $row) => (float) $row->quantity,        'total' => fn () => $this->totalQty],
            'unit_cost'             => ['heading' => 'Cost / Unit',       'width' => 16, 'value' => fn (InvoiceProductCost $row) => (float) $row->unit_cost],
            'unit_price'            => ['heading' => 'Sale Price / Unit', 'width' => 18, 'value' => fn (InvoiceProductCost $row) => (float) ($row->invoiceProduct?->unit_price ?? 0)],
            'cost'                  => ['heading' => 'Cost',              'width' => 18, 'value' => fn (InvoiceProductCost $row) => $this->cost($row),    'total' => fn () => round($this->totalCost, 2)],
            'revenue'               => ['heading' => 'Revenue',           'width' => 18, 'value' => fn (InvoiceProductCost $row) => $this->revenue($row), 'total' => fn () => round($this->totalRevenue, 2)],
            'profit'                => ['heading' => 'Profit',            'width' => 18, 'value' => fn (InvoiceProductCost $row) => round($this->revenue($row) - $this->cost($row), 2), 'total' => fn () => round($this->totalProfit, 2)],
        ];
    }

    private function formatTags(InvoiceProductCost $row): string
    {
        $tags = $row->invoiceProduct?->product?->tags ?? [];

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

        $leading = [$this->orderNumberColumn()];
        if ($this->includeStore) {
            $leading[] = $this->storeColumn();
        }

        return array_merge($leading, $selected);
    }
}
