<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The downloadable "fill this in" template: a bold header row (required columns
 * marked with " *") plus one or more example rows. Headings and rows arrive
 * already built and aligned by ImportService.
 */
class ImportTemplate implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    /**
     * @param  string[]  $headings  display headers, required ones suffixed with " *"
     * @param  list<array<int, string>>  $rows  example rows, positionally aligned to $headings
     */
    public function __construct(
        private array $headings,
        private array $rows = [],
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
