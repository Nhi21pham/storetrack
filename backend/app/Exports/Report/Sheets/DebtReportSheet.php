<?php

namespace App\Exports\Report\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * One tab of the debt-report workbook. Reproduces BaseExport's look — bold
 * title row, italic meta lines, a styled header row and a bold TOTAL row — but
 * is built for the multi-sheet flow: each tab carries its own WithTitle (tab
 * name) and feeds rows in memory (FromArray) rather than streaming a query.
 *
 * Subclasses build their rows (date-windowed) in their constructor and expose
 * them via array(), with the tab name, headings, widths and totals.
 */
abstract class DebtReportSheet implements FromArray, WithHeadings, WithTitle, WithEvents
{
    /** Shared grey fill for the header row and the TOTAL row (matches BaseExport). */
    private const HEADER_FILL_RGB = 'E5E7EB';

    public function __construct(
        protected string $bannerTitle,
        protected array $metaLines,
        protected ?string $startDate = null,
        protected ?string $endDate = null,
    ) {}

    /** The Excel tab name (WithTitle). */
    abstract public function title(): string;

    /** @return array<int, array<int, mixed>> data rows (no headings, no totals) */
    abstract public function array(): array;

    /** @return string[] */
    abstract public function headings(): array;

    /** @return array<int, float|int> 1-based column widths */
    abstract public function columnWidths(): array;

    /** @return array<int, string|int|float> the TOTAL row aligned to the columns; [] for none */
    abstract protected function totalsRow(): array;

    /** A date (Carbon) falls within the report's window. */
    protected function inRange($date): bool
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

    public function registerEvents(): array
    {
        $title = $this->bannerTitle;
        $metaLines = $this->metaLines;
        $columnHeadings = $this->headings();
        $columnWidths = $this->columnWidths();
        $columnCount = max(count($columnHeadings), 1);

        return [
            AfterSheet::class => function (AfterSheet $event) use ($title, $metaLines, $columnCount, $columnWidths) {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = Coordinate::stringFromColumnIndex($columnCount);

                $rowsToInsert = 1 + count($metaLines) + 1;
                $sheet->insertNewRowBefore(1, $rowsToInsert);

                $titleRange = "A1:{$lastColumn}1";
                $sheet->setCellValue('A1', $title);
                $sheet->mergeCells($titleRange);
                $sheet->getStyle($titleRange)->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle($titleRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                foreach ($metaLines as $i => $line) {
                    $row = 2 + $i;
                    $range = "A{$row}:{$lastColumn}{$row}";
                    $sheet->setCellValue("A{$row}", $line);
                    $sheet->mergeCells($range);
                    $sheet->getStyle($range)->getFont()->setItalic(true)->getColor()->setRGB('555555');
                }

                $headingsRow = 2 + count($metaLines) + 1;
                $headingsRange = "A{$headingsRow}:{$lastColumn}{$headingsRow}";
                $sheet->getStyle($headingsRange)->getFont()->setBold(true);
                $sheet->getStyle($headingsRange)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB(self::HEADER_FILL_RGB);

                foreach (range(1, $columnCount) as $i) {
                    $width = $columnWidths[$i] ?? 24;
                    $letter = Coordinate::stringFromColumnIndex($i);
                    $sheet->getColumnDimension($letter)->setWidth($width);
                }

                $totals = $this->totalsRow();
                if ($totals !== []) {
                    $totalsRow = $sheet->getHighestRow() + 2;
                    foreach (array_values($totals) as $index => $value) {
                        $letter = Coordinate::stringFromColumnIndex($index + 1);
                        $sheet->setCellValue("{$letter}{$totalsRow}", $value);
                    }
                    $totalsRange = "A{$totalsRow}:{$lastColumn}{$totalsRow}";
                    $sheet->getStyle($totalsRange)->getFont()->setBold(true);
                    $sheet->getStyle($totalsRange)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB(self::HEADER_FILL_RGB);
                }
            },
        ];
    }

    /** Distinct store names across a party's in-scope invoices and payments (business export only). */
    protected function partyStoreNames($party): string
    {
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
}
