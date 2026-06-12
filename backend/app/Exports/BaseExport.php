<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Reusable base for streamed exports that need:
 *   - a bold title row spanning all columns
 *   - meta lines underneath (e.g. exporter, date)
 *   - a styled column-header row
 *   - body rows fed from a query in chunks
 *
 * Subclasses implement title(), metaLines(), columnHeadings(), exportQuery(), map().
 *
 * Note: this is meant to be invoked from a regular Laravel queued Job that calls
 * Excel::store(...) synchronously inside its handle() — not from Maatwebsite's
 * "queue export" flow — so the closure inside registerEvents() is safe.
 */
abstract class BaseExport implements FromQuery, WithChunkReading, WithEvents, WithHeadings, WithMapping
{
    /** Shared grey fill for the column-header row and the totals row (keeps them consistent). */
    private const HEADER_FILL_RGB = 'E5E7EB';

    abstract public function title(): string;

    /** @return string[] */
    abstract public function metaLines(): array;

    /** @return string[] */
    abstract public function columnHeadings(): array;

    abstract public function exportQuery(): Builder;

    /**
     * @param  mixed  $row
     */
    abstract public function map($row): array;

    public function query(): Builder
    {
        return $this->exportQuery();
    }

    public function headings(): array
    {
        return $this->columnHeadings();
    }

    public function chunkSize(): int
    {
        return 500;
    }

    /**
     * Per-column widths in Excel character units. Override in subclasses to
     * tune layout. Indexed by 1-based column position. Defaults to 24 for
     * any column without a specific value.
     *
     * @return array<int, float|int>
     */
    public function columnWidths(): array
    {
        return [];
    }

    /**
     * Optional summary row appended (after a blank gap) below the body, aligned
     * to the columns. Return [] for no totals row. Compute the values during
     * map() — this is read in the AfterSheet hook, after every row is mapped.
     *
     * @return array<int, string|int|float>
     */
    protected function totalsRow(): array
    {
        return [];
    }

    public function registerEvents(): array
    {
        $title = $this->title();
        $metaLines = $this->metaLines();
        $columnHeadings = $this->columnHeadings();
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

                // Summary row below the body (values were accumulated during map()).
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

    /**
     * Build a safe filename slug from a display name.
     */
    public static function slugForFilename(string $name): string
    {
        $slug = Str::slug($name, '-');

        return $slug !== '' ? $slug : 'unnamed';
    }
}
