<?php

namespace App\Exports\Report\Sheets;

use Illuminate\Database\Eloquent\Collection;

/**
 * "Payments" tab: one row per in-range payment across all parties — the right
 * block of every on-screen expand, flattened, with the invoice codes each
 * payment was applied to. At business level a Store column is added.
 */
class DebtPaymentSheet extends DebtReportSheet
{
    /** @var array<int, array<int, mixed>> */
    private array $rows = [];

    private float $total = 0;

    public function __construct(
        Collection $parties,
        string $bannerTitle,
        array $metaLines,
        private string $partyLabel,
        ?string $startDate,
        ?string $endDate,
        private bool $includeStore = false,
    ) {
        parent::__construct($bannerTitle, $metaLines, $startDate, $endDate);
        $this->build($parties);
    }

    public function title(): string
    {
        return 'Payments';
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        $headings = ['No.', $this->partyLabel, 'Phone'];
        if ($this->includeStore) {
            $headings[] = 'Store';
        }
        array_push($headings, 'Date', 'Method', 'Applied to', 'Amount');

        return $headings;
    }

    public function columnWidths(): array
    {
        $widths = [8, 26, 18];
        if ($this->includeStore) {
            $widths[] = 24;
        }
        array_push($widths, 16, 14, 30, 18);

        $out = [];
        foreach (array_values($widths) as $index => $width) {
            $out[$index + 1] = $width;
        }

        return $out;
    }

    protected function totalsRow(): array
    {
        $row = ['TOTAL', '', ''];
        if ($this->includeStore) {
            $row[] = '';
        }
        array_push($row, '', '', '', round($this->total, 2));

        return $row;
    }

    private function build(Collection $parties): void
    {
        $counter = 0;

        foreach ($parties as $record) {
            $name = (string) ($record->name ?? '');
            $phone = (string) ($record->phone ?? '');
            foreach ($record->party?->payments ?? [] as $payment) {
                if (!$this->inRange($payment->paid_at)) {
                    continue;
                }

                $amount = round((float) $payment->amount, 2);
                $this->total += $amount;

                $applied = collect($payment->allocations)
                    ->map(fn ($allocation) => $allocation->invoice?->code)
                    ->filter()
                    ->implode(', ');

                $row = [++$counter, $name, $phone];
                if ($this->includeStore) {
                    $row[] = (string) ($payment->store?->name ?? '');
                }
                array_push(
                    $row,
                    optional($payment->paid_at)->format('Y-m-d') ?? '',
                    ucfirst((string) ($payment->method?->value ?? '')),
                    $applied,
                    $amount,
                );

                $this->rows[] = $row;
            }
        }
    }
}
