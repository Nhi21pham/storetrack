<?php

namespace App\Repositories\Report;

use App\Enums\InvoiceTypeEnum;
use App\Support\ReportExportOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reads parties (customers or suppliers) together with the invoices and payments
 * that make up their debt, for the debt reports. Each row is one party; its
 * eager-loaded invoices and payments are the full set for the scope (a single
 * store, or every store in a business) and are NOT date-filtered here — the
 * report/export narrows them to the chosen date range and derives the
 * spent/paid/owe totals from that window.
 *
 * Shared by both the customer (receivable / SALE) and supplier (payable /
 * PURCHASE) reports: pass the party model class and the invoice type.
 */
class DebtReportRepository
{
    /** Every party linked to the store, with that store's invoices/payments. */
    public function allForStore(string $partyModel, InvoiceTypeEnum $type, int $storeId): Collection
    {
        return $this->storeBaseQuery($partyModel, $type, $storeId)->get();
    }

    /** Every party in the business, with invoices/payments across all its stores. */
    public function allForBusiness(string $partyModel, InvoiceTypeEnum $type, int $businessId): Collection
    {
        return $this->businessBaseQuery($partyModel, $type, $businessId)->get();
    }

    /**
     * A filtered, ordered query of parties for a store. Used by the export job,
     * which streams it in chunks, so this returns a Builder rather than a result.
     */
    public function listQueryForStore(string $partyModel, InvoiceTypeEnum $type, int $storeId, array $filters = []): Builder
    {
        return $this->applyFilters($this->storeBaseQuery($partyModel, $type, $storeId), $filters);
    }

    /** As listQueryForStore(), but scoped to every store in the business. */
    public function listQueryForBusiness(string $partyModel, InvoiceTypeEnum $type, int $businessId, array $filters = []): Builder
    {
        return $this->applyFilters($this->businessBaseQuery($partyModel, $type, $businessId), $filters);
    }

    private function applyFilters(Builder $query, array $filters): Builder
    {
        $ids = $filters['ids'] ?? null;
        if (is_array($ids) && count($ids) > 0) {
            $query->whereIn('party_id', $ids);
            ReportExportOrder::byIds($query, 'party_id', $ids);
        }

        $search = $filters['search'] ?? null;
        if (is_string($search) && $search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('tax_code', 'like', $like);
            });
        }

        return $query;
    }

    private function storeBaseQuery(string $partyModel, InvoiceTypeEnum $type, int $storeId): Builder
    {
        return $partyModel::query()
            ->whereHas('stores', fn (Builder $q) => $q->where('stores.id', $storeId))
            ->with([
                'party.invoices' => fn ($q) => $q
                    ->where('type', $type->value)
                    ->where('store_id', $storeId)
                    ->orderBy('invoice_date')
                    ->orderBy('id'),
                'party.payments' => fn ($q) => $q
                    ->where('store_id', $storeId)
                    ->orderBy('paid_at')
                    ->orderBy('id'),
                'party.payments.allocations.invoice:id,code',
            ])
            ->orderBy('name');
    }

    private function businessBaseQuery(string $partyModel, InvoiceTypeEnum $type, int $businessId): Builder
    {
        return $partyModel::query()
            ->where('business_id', $businessId)
            ->with([
                'party.invoices' => fn ($q) => $q
                    ->where('type', $type->value)
                    ->whereHas('store', fn (Builder $s) => $s->where('business_id', $businessId))
                    ->orderBy('invoice_date')
                    ->orderBy('id'),
                'party.invoices.store:id,name',
                'party.payments' => fn ($q) => $q
                    ->whereHas('store', fn (Builder $s) => $s->where('business_id', $businessId))
                    ->orderBy('paid_at')
                    ->orderBy('id'),
                'party.payments.store:id,name',
                'party.payments.allocations.invoice:id,code',
            ])
            ->orderBy('name');
    }
}
