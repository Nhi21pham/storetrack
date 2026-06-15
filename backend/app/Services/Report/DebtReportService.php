<?php

namespace App\Services\Report;

use App\Enums\ExportScope;
use App\Enums\InvoiceTypeEnum;
use App\Enums\PermissionEnum;
use App\Exports\Report\DebtReportExport;
use App\Jobs\Exports\ExportDebtReportJob;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Export;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Repositories\Report\DebtReportRepository;
use App\Services\ExportService;
use App\Services\PermissionService;
use Illuminate\Database\Eloquent\Model;

/**
 * Backs the two debt reports — customer (receivable, SALE) and supplier
 * (payable, PURCHASE) — which share one shape: one row per party carrying its
 * invoices and payments for the scope. The frontend/export narrow those to a
 * date range and derive spent / paid / owe; this service just projects the rows.
 */
class DebtReportService
{
    public function __construct(
        private DebtReportRepository $debtReportRepository,
        private PermissionService $permissionService,
        private ExportService $exportService,
    ) {}

    public function getReceivablesReport(User $user, int $storeId): array
    {
        return $this->storeReport($user, Customer::class, InvoiceTypeEnum::SALE, $storeId);
    }

    public function getReceivablesReportByBusiness(User $user, int $businessId): array
    {
        return $this->businessReport($user, Customer::class, InvoiceTypeEnum::SALE, $businessId);
    }

    public function getPayablesReport(User $user, int $storeId): array
    {
        return $this->storeReport($user, Supplier::class, InvoiceTypeEnum::PURCHASE, $storeId);
    }

    public function getPayablesReportByBusiness(User $user, int $businessId): array
    {
        return $this->businessReport($user, Supplier::class, InvoiceTypeEnum::PURCHASE, $businessId);
    }

    public function queueReceivablesExport(User $user, int $storeId, array $filters = [], ?string $clientId = null): Export
    {
        return $this->queueStore($user, ExportDebtReportJob::TYPE_RECEIVABLES_STORE, $storeId, $filters, $clientId);
    }

    public function queueReceivablesBusinessExport(User $user, int $businessId, array $filters = [], ?string $clientId = null): Export
    {
        return $this->queueBusiness($user, ExportDebtReportJob::TYPE_RECEIVABLES_BUSINESS, $businessId, $filters, $clientId);
    }

    public function queuePayablesExport(User $user, int $storeId, array $filters = [], ?string $clientId = null): Export
    {
        return $this->queueStore($user, ExportDebtReportJob::TYPE_PAYABLES_STORE, $storeId, $filters, $clientId);
    }

    public function queuePayablesBusinessExport(User $user, int $businessId, array $filters = [], ?string $clientId = null): Export
    {
        return $this->queueBusiness($user, ExportDebtReportJob::TYPE_PAYABLES_BUSINESS, $businessId, $filters, $clientId);
    }

    private function storeReport(User $user, string $partyModel, InvoiceTypeEnum $type, int $storeId): array
    {
        $this->permissionService->authorizeStore($user, PermissionEnum::CREATE_PAYMENT, $storeId);

        return $this->debtReportRepository->allForStore($partyModel, $type, $storeId)
            ->map(fn (Model $party) => $this->toRow($party, false))
            ->all();
    }

    private function businessReport(User $user, string $partyModel, InvoiceTypeEnum $type, int $businessId): array
    {
        $this->permissionService->authorizeBusinessOwner($user, $businessId);

        return $this->debtReportRepository->allForBusiness($partyModel, $type, $businessId)
            ->map(fn (Model $party) => $this->toRow($party, true))
            ->all();
    }

    private function queueStore(User $user, string $type, int $storeId, array $filters, ?string $clientId): Export
    {
        $this->permissionService->authorizeStore($user, PermissionEnum::CREATE_PAYMENT, $storeId);

        return $this->exportService->queue(
            $user,
            $type,
            ExportScope::STORE,
            $storeId,
            Store::find($storeId)?->name,
            $this->normalizeExportFilters($filters),
            ExportDebtReportJob::class,
            $clientId,
        );
    }

    private function queueBusiness(User $user, string $type, int $businessId, array $filters, ?string $clientId): Export
    {
        $this->permissionService->authorizeBusinessOwner($user, $businessId);

        return $this->exportService->queue(
            $user,
            $type,
            ExportScope::BUSINESS,
            $businessId,
            Business::find($businessId)?->name,
            $this->normalizeExportFilters($filters),
            ExportDebtReportJob::class,
            $clientId,
        );
    }

    /** Projects a party (customer/supplier) into the report row shape exposed by GraphQL. */
    private function toRow(Model $record, bool $includeStore): array
    {
        $party = $record->party;

        $invoices = ($party?->invoices ?? collect())->map(fn ($invoice) => [
            'id'           => (int) $invoice->id,
            'code'         => $invoice->code,
            'invoice_date' => $invoice->invoice_date,
            'grand_total'  => (float) $invoice->grand_total,
            'store_id'     => $invoice->store_id !== null ? (int) $invoice->store_id : null,
            'store_name'   => $includeStore ? $invoice->store?->name : null,
        ])->all();

        $payments = ($party?->payments ?? collect())->map(fn ($payment) => [
            'id'          => (int) $payment->id,
            'paid_at'     => $payment->paid_at,
            'amount'      => (float) $payment->amount,
            'method'      => $payment->method?->value,
            'store_id'    => $payment->store_id !== null ? (int) $payment->store_id : null,
            'store_name'  => $includeStore ? $payment->store?->name : null,
            'allocations' => $payment->allocations->map(fn ($allocation) => [
                'invoice_id'   => (int) $allocation->invoice_id,
                'invoice_code' => $allocation->invoice?->code,
                'amount'       => (float) $allocation->amount,
            ])->all(),
        ])->all();

        return [
            'party_id'        => $record->party_id !== null ? (int) $record->party_id : null,
            'party_record_id' => (int) $record->id,
            'name'            => $record->name,
            'phone'           => $record->phone,
            'email'           => $record->email,
            'invoices'        => $invoices,
            'payments'        => $payments,
        ];
    }

    private function normalizeExportFilters(array $filters): array
    {
        $clean = [];

        if (!empty($filters['search'])) {
            $clean['search'] = (string) $filters['search'];
        }

        if (!empty($filters['start_date'])) {
            $clean['start_date'] = (string) $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $clean['end_date'] = (string) $filters['end_date'];
        }

        if (is_array($filters['ids'] ?? null) && count($filters['ids']) > 0) {
            $clean['ids'] = array_values(array_map('intval', $filters['ids']));
        }

        if (is_array($filters['columns'] ?? null)) {
            $columns = array_values(array_filter(
                $filters['columns'],
                fn ($column) => in_array($column, DebtReportExport::COLUMN_KEYS, true),
            ));
            if ($columns !== []) {
                $clean['columns'] = $columns;
            }
        }

        return $clean;
    }
}
