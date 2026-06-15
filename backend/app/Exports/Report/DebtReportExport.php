<?php

namespace App\Exports\Report;

use App\Exports\BaseExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Generates a debt-report Excel export: one row per party (customer/supplier),
 * with spent / paid / owe derived from the party's invoices and payments
 * narrowed to the report's date range — the same windowing the frontend does.
 */
class DebtReportExport extends BaseExport
{
    public const COLUMN_KEYS = [
        'phone', 'invoice_count', 'spent', 'paid', 'owe', 'email',
    ];

    /** Running ordinal (STT) assigned to rows in export order. */
    private int $counter = 0;

    /** Running totals accumulated during map(), emitted in the summary row. */
    private float $totalSpent = 0;
    private float $totalPaid = 0;
    private float $totalOwe = 0;

    /** Per-row windowed figures, recomputed at the start of each map() call. */
    private float $rowSpent = 0;
    private float $rowPaid = 0;
    private float $rowOwe = 0;
    private int $rowInvoiceCount = 0;

    /**
     * @param  Builder  $query  filtered, ordered query of Customer/Supplier
     * @param  string  $title  e.g. "Customer debt report of store My Store"
     * @param  string[]  $metaLines
     * @param  string  $partyLabel  "Customer" or "Supplier"
     * @param  string  $spentLabel  "Total Spent" or "Total Purchased"
     * @param  string[]|null  $columns  selected column keys; null exports every column
     * @param  bool  $includeStore  add a Store column (consolidated business export)
     */
    public function __construct(
        private Builder $query,
        private string $title,
        private array $metaLines,
        private string $partyLabel,
        private string $spentLabel,
        private ?string $startDate = null,
        private ?string $endDate = null,
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
     * @param  Model  $row
     */
    public function map($row): array
    {
        $this->computeRow($row);

        $this->totalSpent += $this->rowSpent;
        $this->totalPaid += $this->rowPaid;
        $this->totalOwe += $this->rowOwe;

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

    /** Narrows the party's invoices/payments to the date range and caches the figures for this row. */
    private function computeRow(Model $record): void
    {
        $party = $record->party;

        $spent = 0.0;
        $count = 0;
        foreach ($party?->invoices ?? [] as $invoice) {
            if ($this->inRange($invoice->invoice_date)) {
                $spent += (float) $invoice->grand_total;
                $count++;
            }
        }

        $paid = 0.0;
        foreach ($party?->payments ?? [] as $payment) {
            if ($this->inRange($payment->paid_at)) {
                $paid += (float) $payment->amount;
            }
        }

        $this->rowSpent = round($spent, 2);
        $this->rowPaid = round($paid, 2);
        // Owe never goes below 0 (mirrors the on-screen report).
        $this->rowOwe = round(max(0, $spent - $paid), 2);
        $this->rowInvoiceCount = $count;
    }

    private function inRange($date): bool
    {
        if ($date === null) {
            return false;
        }
        $day = $date->format('Y-m-d');
        if ($this->startDate !== null && $day < $this->startDate) {
            return false;
        }
        if ($this->endDate !== null && $day > $this->endDate) {
            return false;
        }

        return true;
    }

    /** The STT column is always first, independent of the selectable columns. */
    private function orderNumberColumn(): array
    {
        return ['heading' => 'No.', 'width' => 8, 'value' => fn (Model $row) => ++$this->counter];
    }

    /** The Store column is added for consolidated business exports, after the STT column. */
    private function storeColumn(): array
    {
        return ['heading' => 'Store', 'width' => 24, 'value' => fn (Model $row) => $this->storeNames($row)];
    }

    /** The party name is always shown, right after the STT/Store columns. */
    private function nameColumn(): array
    {
        return ['heading' => $this->partyLabel, 'width' => 28, 'value' => fn (Model $row) => (string) ($row->name ?? '')];
    }

    private function columnDefinitions(): array
    {
        return [
            'phone'         => ['heading' => 'Phone',        'width' => 18, 'value' => fn (Model $row) => (string) ($row->phone ?? '')],
            'invoice_count' => ['heading' => '# Invoices',   'width' => 12, 'value' => fn (Model $row) => $this->rowInvoiceCount],
            'spent'         => ['heading' => $this->spentLabel, 'width' => 18, 'value' => fn (Model $row) => $this->rowSpent, 'total' => fn () => round($this->totalSpent, 2)],
            'paid'          => ['heading' => 'Total Paid',   'width' => 18, 'value' => fn (Model $row) => $this->rowPaid,  'total' => fn () => round($this->totalPaid, 2)],
            'owe'           => ['heading' => 'Total Owe',    'width' => 18, 'value' => fn (Model $row) => $this->rowOwe,   'total' => fn () => round($this->totalOwe, 2)],
            'email'         => ['heading' => 'Email',        'width' => 26, 'value' => fn (Model $row) => (string) ($row->email ?? '')],
        ];
    }

    /** Distinct store names across the party's in-scope invoices and payments (business export only). */
    private function storeNames(Model $record): string
    {
        $party = $record->party;
        $names = [];
        foreach ($party?->invoices ?? [] as $invoice) {
            if ($invoice->store?->name) {
                $names[$invoice->store->name] = true;
            }
        }
        foreach ($party?->payments ?? [] as $payment) {
            if ($payment->store?->name) {
                $names[$payment->store->name] = true;
            }
        }

        return implode(', ', array_keys($names));
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
        $leading[] = $this->nameColumn();

        return array_merge($leading, $selected);
    }
}
