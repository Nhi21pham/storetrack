<?php

namespace App\Exports;

use App\Enums\PartyTypeEnum;
use App\Models\BankAccount;
use Illuminate\Database\Eloquent\Builder;

class BankAccountExport extends BaseExport
{
    public const COLUMN_KEYS = ['owner', 'owner_name', 'bank', 'account_number', 'holder_name', 'branch', 'province', 'created_at', 'updated_at'];

    /**
     * @param  Builder  $query  filtered, ordered query of BankAccount
     * @param  string  $title  e.g. "Bank accounts of business My Business"
     * @param  string[]  $metaLines
     * @param  string[]|null  $columns  selected column keys; null exports every column
     */
    public function __construct(
        private Builder $query,
        private string $title,
        private array $metaLines,
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
     * @param  BankAccount  $row
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
            'owner'          => ['heading' => 'Owner Type',     'width' => 14, 'value' => fn ($row) => $this->ownerType($row)],
            'owner_name'     => ['heading' => 'Owner Name',     'width' => 26, 'value' => fn ($row) => $this->ownerName($row)],
            'bank'           => ['heading' => 'Bank',           'width' => 18, 'value' => fn ($row) => (string) ($row->bank?->short_name ?? '')],
            'account_number' => ['heading' => 'Account Number', 'width' => 22, 'value' => fn ($row) => (string) ($row->account_number ?? '')],
            'holder_name'    => ['heading' => 'Holder Name',    'width' => 24, 'value' => fn ($row) => (string) ($row->account_holder_name ?? '')],
            'branch'         => ['heading' => 'Branch',         'width' => 24, 'value' => fn ($row) => (string) ($row->branch ?? '')],
            'province'       => ['heading' => 'Province',       'width' => 22, 'value' => fn ($row) => (string) ($row->province?->name_vi ?? '')],
            'created_at'     => ['heading' => 'Created',        'width' => 20, 'value' => fn ($row) => optional($row->created_at)->format('Y-m-d H:i') ?? ''],
            'updated_at'     => ['heading' => 'Updated',        'width' => 20, 'value' => fn ($row) => optional($row->updated_at)->format('Y-m-d H:i') ?? ''],
        ];
    }

    private function partyType(BankAccount $row): ?PartyTypeEnum
    {
        $type = $row->party?->type;
        if ($type === null) {
            return null;
        }
        return $type instanceof PartyTypeEnum ? $type : PartyTypeEnum::from((string) $type);
    }

    private function ownerType(BankAccount $row): string
    {
        return match ($this->partyType($row)) {
            PartyTypeEnum::BUSINESS => 'Business',
            PartyTypeEnum::CUSTOMER => 'Customer',
            PartyTypeEnum::SUPPLIER => 'Supplier',
            default                 => '',
        };
    }

    private function ownerName(BankAccount $row): string
    {
        $party = $row->party;

        return (string) (match ($this->partyType($row)) {
            PartyTypeEnum::BUSINESS => $party?->business?->name,
            PartyTypeEnum::CUSTOMER => $party?->customer?->name,
            PartyTypeEnum::SUPPLIER => $party?->supplier?->name,
            default                 => null,
        } ?? '');
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
