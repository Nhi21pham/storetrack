<?php

namespace App\Exports;

use App\Models\Invoice\Invoice;
use Illuminate\Database\Eloquent\Builder;

class InvoiceExport extends BaseExport
{
    public const COLUMN_KEYS = [
        'code', 'invoice_date', 'party_name', 'payment_method',
        'payment_status', 'subtotal', 'tax_total', 'grand_total', 'created_at',
    ];

    /**
     * @param  Builder  $query  filtered, ordered query of Invoice
     * @param  string  $title  e.g. "Purchase invoices of store My Store"
     * @param  string[]  $metaLines
     * @param  string  $partyLabel  heading for the party column ("Supplier" for
     *                               purchases, "Customer" for sales)
     * @param  string[]|null  $columns  selected column keys; null exports every column
     */
    public function __construct(
        private Builder $query,
        private string $title,
        private array $metaLines,
        private string $partyLabel = 'Party',
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
     * @param  Invoice  $row
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
            'code'           => ['heading' => 'Code',          'width' => 18, 'value' => fn ($row) => (string) $row->code],
            'invoice_date'   => ['heading' => 'Date',          'width' => 14, 'value' => fn ($row) => optional($row->invoice_date)->format('Y-m-d') ?? ''],
            'party_name'     => ['heading' => $this->partyLabel, 'width' => 28, 'value' => fn ($row) => (string) ($row->party_name ?? '')],
            'payment_method' => ['heading' => 'Payment',       'width' => 14, 'value' => fn ($row) => ucfirst($row->payment_method->value)],
            'payment_status' => ['heading' => 'Status',        'width' => 14, 'value' => fn ($row) => ucfirst($row->payment_status->value)],
            'subtotal'       => ['heading' => 'Subtotal',      'width' => 16, 'value' => fn ($row) => (float) $row->subtotal],
            'tax_total'      => ['heading' => 'Tax',           'width' => 16, 'value' => fn ($row) => (float) $row->tax_total],
            'grand_total'    => ['heading' => 'Grand Total',   'width' => 18, 'value' => fn ($row) => (float) $row->grand_total],
            'created_at'     => ['heading' => 'Created',       'width' => 20, 'value' => fn ($row) => optional($row->created_at)->format('Y-m-d H:i') ?? ''],
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
