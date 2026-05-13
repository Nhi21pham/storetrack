<?php

namespace App\Exports;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;

class CustomerExport extends BaseExport
{
    /**
     * @param  Builder  $query  filtered, ordered query of Customer
     * @param  string  $title  e.g. "Customers of My Store"
     * @param  string[]  $metaLines
     */
    public function __construct(
        private Builder $query,
        private string $title,
        private array $metaLines,
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
        return ['ID', 'Name', 'Tax Code', 'Email', 'Phone', 'Created', 'Last Updated'];
    }

    public function exportQuery(): Builder
    {
        return $this->query;
    }

    /**
     * @param  Customer  $row
     */
    public function map($row): array
    {
        return [
            (int) $row->id,
            (string) $row->name,
            (string) ($row->tax_code ?? ''),
            (string) ($row->email ?? ''),
            (string) ($row->phone ?? ''),
            optional($row->created_at)->format('Y-m-d H:i:s') ?? '',
            optional($row->updated_at)->format('Y-m-d H:i:s') ?? '',
        ];
    }

    public function columnWidths(): array
    {
        return [1 => 8, 2 => 26, 3 => 18, 4 => 28, 5 => 16, 6 => 20, 7 => 20];
    }
}
