<?php

namespace App\Exports\Report;

use App\Exports\Contracts\Exportable;
use App\Exports\Report\Sheets\DebtInvoiceSheet;
use App\Exports\Report\Sheets\DebtPaymentSheet;
use App\Exports\Report\Sheets\DebtSummarySheet;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * The debt-report workbook: a "Summary" tab (one row per party with
 * date-windowed Spent/Paid/Owe) plus "Invoices" and "Payments" detail tabs
 * (the on-screen expand, flattened). All three derive from the same parties
 * collection and date window.
 */
class DebtReportExport implements Exportable, WithMultipleSheets
{
    /** Selectable summary columns — re-exposed for the service's filter validation. */
    public const COLUMN_KEYS = DebtSummarySheet::COLUMN_KEYS;

    /**
     * @param  Collection  $parties  customers/suppliers with eager-loaded party.invoices + party.payments
     * @param  string  $title  e.g. "Customer debt report of store My Store"
     * @param  string[]  $metaLines
     * @param  string  $partyLabel  "Customer" or "Supplier"
     * @param  string  $spentLabel  "Total Spent" or "Total Purchased"
     * @param  string[]|null  $columns  selected summary column keys; null = all
     * @param  bool  $includeStore  add a Store column (consolidated business export)
     */
    public function __construct(
        private Collection $parties,
        private string $title,
        private array $metaLines,
        private string $partyLabel,
        private string $spentLabel,
        private ?string $startDate = null,
        private ?string $endDate = null,
        private ?array $columns = null,
        private bool $includeStore = false,
    ) {}

    /** @return \Maatwebsite\Excel\Concerns\WithTitle[] */
    public function sheets(): array
    {
        return [
            new DebtSummarySheet(
                $this->parties, $this->title, $this->metaLines,
                $this->partyLabel, $this->spentLabel,
                $this->startDate, $this->endDate, $this->columns, $this->includeStore,
            ),
            new DebtInvoiceSheet(
                $this->parties, $this->title, $this->metaLines,
                $this->partyLabel, $this->startDate, $this->endDate, $this->includeStore,
            ),
            new DebtPaymentSheet(
                $this->parties, $this->title, $this->metaLines,
                $this->partyLabel, $this->startDate, $this->endDate, $this->includeStore,
            ),
        ];
    }
}
