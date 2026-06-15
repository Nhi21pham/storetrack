<?php

namespace App\Exports\Report\Sheets;

use Illuminate\Database\Eloquent\Collection;

/**
 * "Invoices" tab: one row per in-range invoice across all parties — the left
 * block of every on-screen expand, flattened. At business level a Store column
 * is added (a party can span stores).
 */
class DebtInvoiceSheet extends DebtReportSheet
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
        return 'Invoices';
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        $headings = ['No.', $this->partyLabel];
        if ($this->includeStore) {
            $headings[] = 'Store';
        }
        array_push($headings, 'Invoice', 'Date', 'Amount');

        return $headings;
    }

    public function columnWidths(): array
    {
        $widths = [8, 26];
        if ($this->includeStore) {
            $widths[] = 24;
        }
        array_push($widths, 18, 16, 18);

        $out = [];
        foreach (array_values($widths) as $index => $width) {
            $out[$index + 1] = $width;
        }

        return $out;
    }

    protected function totalsRow(): array
    {
        $row = ['TOTAL', ''];
        if ($this->includeStore) {
            $row[] = '';
        }
        array_push($row, '', '', round($this->total, 2));

        return $row;
    }

    private function build(Collection $parties): void
    {
        $counter = 0;

        foreach ($parties as $record) {
            $name = (string) ($record->name ?? '');
            foreach ($record->party?->invoices ?? [] as $invoice) {
                if (!$this->inRange($invoice->invoice_date)) {
                    continue;
                }

                $amount = round((float) $invoice->grand_total, 2);
                $this->total += $amount;

                $row = [++$counter, $name];
                if ($this->includeStore) {
                    $row[] = (string) ($invoice->store?->name ?? '');
                }
                array_push(
                    $row,
                    (string) ($invoice->code ?? ''),
                    optional($invoice->invoice_date)->format('Y-m-d') ?? '',
                    $amount,
                );

                $this->rows[] = $row;
            }
        }
    }
}
