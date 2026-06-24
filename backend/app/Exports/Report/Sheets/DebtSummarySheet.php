<?php

namespace App\Exports\Report\Sheets;

use Illuminate\Database\Eloquent\Collection;

/**
 * "Summary" tab: one row per party with date-windowed Spent / Paid / Owe,
 * honoring the report's selected (visible) columns. Mirrors the on-screen
 * per-party rows.
 */
class DebtSummarySheet extends DebtReportSheet
{
    /** Selectable columns, in display order; keys match the frontend column keys. */
    public const COLUMN_KEYS = ['phone', 'email', 'invoice_count', 'spent', 'paid', 'owe'];

    /** @var array<int, array<int, mixed>> */
    private array $rows = [];

    private float $totalSpent = 0;
    private float $totalPaid = 0;
    private float $totalOwe = 0;

    public function __construct(
        Collection $parties,
        string $bannerTitle,
        array $metaLines,
        private string $partyLabel,
        private string $spentLabel,
        ?string $startDate,
        ?string $endDate,
        private ?array $columns = null,
        private bool $includeStore = false,
    ) {
        parent::__construct($bannerTitle, $metaLines, $startDate, $endDate);
        $this->build($parties);
    }

    public function title(): string
    {
        return __('exports.sheet_summary');
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        $headings = [__('exports.col_no')];
        if ($this->includeStore) {
            $headings[] = __('exports.col_store');
        }
        $headings[] = $this->partyLabel;
        foreach ($this->activeKeys() as $key) {
            $headings[] = $this->columnDefinitions()[$key]['heading'];
        }

        return $headings;
    }

    public function columnWidths(): array
    {
        $widths = [8];
        if ($this->includeStore) {
            $widths[] = 24;
        }
        $widths[] = 28;
        foreach ($this->activeKeys() as $key) {
            $widths[] = $this->columnDefinitions()[$key]['width'];
        }

        $out = [];
        foreach (array_values($widths) as $index => $width) {
            $out[$index + 1] = $width;
        }

        return $out;
    }

    protected function totalsRow(): array
    {
        $row = [__('exports.total')];
        if ($this->includeStore) {
            $row[] = '';
        }
        $row[] = '';
        foreach ($this->activeKeys() as $key) {
            $row[] = match ($key) {
                'spent' => round($this->totalSpent, 2),
                'paid'  => round($this->totalPaid, 2),
                'owe'   => round($this->totalOwe, 2),
                default => '',
            };
        }

        return $row;
    }

    private function build(Collection $parties): void
    {
        $counter = 0;

        foreach ($parties as $record) {
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

            $spent = round($spent, 2);
            $paid = round($paid, 2);
            $owe = round(max(0, $spent - $paid), 2);

            $this->totalSpent += $spent;
            $this->totalPaid += $paid;
            $this->totalOwe += $owe;

            $values = [
                'phone'         => (string) ($record->phone ?? ''),
                'invoice_count' => $count,
                'spent'         => $spent,
                'paid'          => $paid,
                'owe'           => $owe,
                'email'         => (string) ($record->email ?? ''),
            ];

            $row = [++$counter];
            if ($this->includeStore) {
                $row[] = $this->partyStoreNames($party);
            }
            $row[] = (string) ($record->name ?? '');
            foreach ($this->activeKeys() as $key) {
                $row[] = $values[$key];
            }

            $this->rows[] = $row;
        }
    }

    /** @return array<string, array{heading: string, width: int}> */
    private function columnDefinitions(): array
    {
        return [
            'phone'         => ['heading' => __('exports.col_phone'),          'width' => 18],
            'invoice_count' => ['heading' => __('exports.col_num_invoices'),     'width' => 12],
            'spent'         => ['heading' => $this->spentLabel, 'width' => 18],
            'paid'          => ['heading' => __('exports.col_total_paid'),     'width' => 18],
            'owe'           => ['heading' => __('exports.col_total_owe'),      'width' => 18],
            'email'         => ['heading' => __('exports.col_email'),          'width' => 26],
        ];
    }

    /** Selected column keys (in canonical order); all of them when none specified. */
    private function activeKeys(): array
    {
        $all = self::COLUMN_KEYS;
        if (empty($this->columns)) {
            return $all;
        }
        $selected = array_values(array_filter($all, fn ($key) => in_array($key, $this->columns, true)));

        return $selected === [] ? $all : $selected;
    }
}
