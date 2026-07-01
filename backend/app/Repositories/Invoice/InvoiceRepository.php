<?php

namespace App\Repositories\Invoice;

use App\Models\Invoice\Invoice;
use App\Models\Invoice\InvoiceProduct;
use App\Models\Invoice\InvoiceProductTax;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class InvoiceRepository
{
    private const RELATIONS = ['party.supplier', 'party.customer', 'creator', 'items.product', 'items.taxes', 'items.costs'];

    private const LIST_RELATIONS = ['party.supplier', 'party.customer', 'creator'];

    public function findById(int $id): ?Invoice
    {
        return Invoice::with(self::RELATIONS)->find($id);
    }

    public function all(int $storeId, ?string $type = null): Collection
    {
        $query = Invoice::with(self::LIST_RELATIONS)->where('store_id', $storeId);
        if ($type !== null) {
            $query->where('type', $type);
        }
        return $query->orderByDesc('id')->get();
    }

    private const DOCUMENT_RELATIONS = ['party.supplier', 'party.customer', 'creator', 'items.taxes', 'items.product.unit'];

    /**
     * A filtered, ordered query of invoices for a store. Used by the export job,
     * which streams it in chunks, so this returns a Builder rather than a result.
     */
    public function listQuery(int $storeId, array $filters = []): Builder
    {
        $query = Invoice::query()
            ->with(self::LIST_RELATIONS)
            ->where('store_id', $storeId);

        return $this->applyListFilters($query, $filters)->orderByDesc('id');
    }

    /**
     * Same filtered, ordered selection as listQuery but eager-loading each
     * invoice's line items and their taxes — for the per-invoice PDF document
     * export, which renders the full content of every matched invoice.
     */
    public function documentsQuery(int $storeId, array $filters = []): Builder
    {
        $query = Invoice::query()
            ->with(self::DOCUMENT_RELATIONS)
            ->where('store_id', $storeId);

        return $this->applyListFilters($query, $filters)->orderByDesc('id');
    }

    /**
     * Applies the shared invoice list filters (type, ids, party, payment,
     * date range, search) to a base query. Ordering is left to the caller.
     */
    private function applyListFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters['type'])) {
            $query->where('type', (string) $filters['type']);
        }

        $ids = $filters['ids'] ?? null;
        if (is_array($ids) && count($ids) > 0) {
            $query->whereIn('id', $ids);
        }

        if (!empty($filters['party_id'])) {
            $query->where('party_id', (int) $filters['party_id']);
        }

        if (!empty($filters['payment_method'])) {
            $query->where('payment_method', (string) $filters['payment_method']);
        }

        if (!empty($filters['payment_status'])) {
            $query->where('payment_status', (string) $filters['payment_status']);
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('invoice_date', '>=', (string) $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('invoice_date', '<=', (string) $filters['end_date']);
        }

        $search = $filters['search'] ?? null;
        if (is_string($search) && $search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $q) use ($like) {
                $q->where('code', 'like', $like)
                    ->orWhereHas('party.supplier', fn ($s) => $s->where('name', 'like', $like))
                    ->orWhereHas('party.customer', fn ($c) => $c->where('name', 'like', $like));
            });
        }

        return $query;
    }

    public function create(array $data): Invoice
    {
        return Invoice::create($data);
    }

    public function updateTotals(Invoice $invoice, array $totals): Invoice
    {
        $invoice->update($totals);
        return $invoice;
    }

    public function lockById(int $id): ?Invoice
    {
        return Invoice::whereKey($id)->lockForUpdate()->first();
    }

    /** A party's still-owing invoices of a type in a store, oldest first — for the payment allocation picker. */
    public function openForParty(int $storeId, int $partyId, string $type): Collection
    {
        return Invoice::where('store_id', $storeId)
            ->where('party_id', $partyId)
            ->where('type', $type)
            ->whereColumn('paid_amount', '<', 'grand_total')
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();
    }

    /** Same as openForParty but across every store in a business — owner business view. */
    public function openForPartyInBusiness(int $businessId, int $partyId, string $type): Collection
    {
        return Invoice::where('party_id', $partyId)
            ->where('type', $type)
            ->whereColumn('paid_amount', '<', 'grand_total')
            ->whereHas('store', fn ($q) => $q->where('business_id', $businessId))
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        $invoice->update($data);
        return $invoice;
    }

    public function deleteItems(Invoice $invoice): void
    {
        $invoice->items()->delete();
    }

    public function delete(Invoice $invoice): void
    {
        $invoice->delete();
    }

    public function createItem(array $data): InvoiceProduct
    {
        return InvoiceProduct::create($data);
    }

    public function updateItemTotals(InvoiceProduct $item, array $totals): InvoiceProduct
    {
        $item->update($totals);
        return $item;
    }

    public function createItemTax(array $data): InvoiceProductTax
    {
        return InvoiceProductTax::create($data);
    }
}
