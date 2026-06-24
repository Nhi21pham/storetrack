<?php

namespace App\Exports\Report;

use App\Exports\BaseExport;
use App\Models\Invoice\InvoiceProduct;
use Illuminate\Database\Eloquent\Builder;

class SaleReportExport extends BaseExport
{
    public const COLUMN_KEYS = [
        'product_name', 'product_code', 'tags', 'customer_name', 'invoice_code',
        'invoice_date', 'quantity', 'unit_price', 'total_sale',
    ];

    /** Running ordinal (STT) assigned to rows in export order. */
    private int $counter = 0;

    /** Running totals accumulated during map(), emitted in the summary row. */
    private float $totalQty = 0;
    private float $totalSale = 0;

    /**
     * @param  Builder  $query  filtered, ordered query of InvoiceProduct
     * @param  string  $title  e.g. "Sale report of store My Store"
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
     * @param  InvoiceProduct  $row
     */
    public function map($row): array
    {
        $this->totalQty += (float) $row->quantity;
        $this->totalSale += round((float) $row->subtotal, 2);

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

    /** The STT column is always first, independent of the selectable columns. */
    private function orderNumberColumn(): array
    {
        return ['heading' => __('exports.col_no'), 'width' => 8, 'value' => fn (InvoiceProduct $row) => ++$this->counter];
    }

    /** The Store column is added for consolidated business exports, after the STT column. */
    private function storeColumn(): array
    {
        return ['heading' => __('exports.col_store'), 'width' => 24, 'value' => fn (InvoiceProduct $row) => (string) ($row->invoice?->store?->name ?? '')];
    }

    private function columnDefinitions(): array
    {
        return [
            'product_name' => ['heading' => __('exports.col_product'),       'width' => 28, 'value' => fn (InvoiceProduct $row) => (string) ($row->product?->name ?? $row->product_name ?? '')],
            'product_code' => ['heading' => __('exports.col_code'),          'width' => 16, 'value' => fn (InvoiceProduct $row) => (string) ($row->product?->code ?? '')],
            'tags'         => ['heading' => __('exports.col_tags'),          'width' => 30, 'value' => fn (InvoiceProduct $row) => $this->formatTags($row)],
            'customer_name' => ['heading' => __('exports.col_customer'),     'width' => 26, 'value' => fn (InvoiceProduct $row) => (string) ($row->invoice?->party_name ?? '')],
            'invoice_code' => ['heading' => __('exports.col_sale_invoice'),  'width' => 18, 'value' => fn (InvoiceProduct $row) => (string) ($row->invoice?->code ?? '')],
            'invoice_date' => ['heading' => __('exports.col_sale_date'),     'width' => 16, 'value' => fn (InvoiceProduct $row) => optional($row->invoice?->invoice_date)->format('Y-m-d') ?? ''],
            'quantity'     => ['heading' => __('exports.col_qty_sold'),      'width' => 14, 'value' => fn (InvoiceProduct $row) => (float) $row->quantity,            'total' => fn () => $this->totalQty],
            'unit_price'   => ['heading' => __('exports.col_sale_price_per_unit'), 'width' => 18, 'value' => fn (InvoiceProduct $row) => (float) $row->unit_price],
            'total_sale'   => ['heading' => __('exports.col_total_sale'),    'width' => 18, 'value' => fn (InvoiceProduct $row) => round((float) $row->subtotal, 2), 'total' => fn () => round($this->totalSale, 2)],
        ];
    }

    private function formatTags(InvoiceProduct $row): string
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
            $row[0] = __('exports.total');
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
