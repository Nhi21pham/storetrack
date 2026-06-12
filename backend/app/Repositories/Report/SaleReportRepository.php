<?php

namespace App\Repositories\Report;

use App\Enums\InvoiceTypeEnum;
use App\Models\Invoice\InvoiceProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reads sold product lines for the sale report. Each row is one line item of a
 * sale invoice, so it carries its product (name/code/tags), the sale invoice
 * (and thus the customer), the sale date, the quantity sold, and the unit price.
 *
 * The same row shape serves two scopes: a single store, or every store in a
 * business (the consolidated owner view, where the invoice's store is loaded so
 * each row can name its store).
 */
class SaleReportRepository
{
    private const RELATIONS = ['product.taggables.tag', 'product.taggables.tagValue', 'invoice.party.customer'];

    /** Every sold line in the store, for the list view. */
    public function all(int $storeId): Collection
    {
        return $this->storeBaseQuery($storeId)->get();
    }

    /** Every sold line across all of the business's stores, for the consolidated list view. */
    public function allForBusiness(int $businessId): Collection
    {
        return $this->businessBaseQuery($businessId)->get();
    }

    /**
     * A filtered, ordered query of sold lines for a store. Used by the export job,
     * which streams it in chunks, so this returns a Builder rather than a result.
     */
    public function listQuery(int $storeId, array $filters = []): Builder
    {
        return $this->applyFilters($this->storeBaseQuery($storeId), $filters);
    }

    /** As listQuery(), but scoped to every store in the business. */
    public function listQueryForBusiness(int $businessId, array $filters = []): Builder
    {
        return $this->applyFilters($this->businessBaseQuery($businessId), $filters);
    }

    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (isset($filters['min_quantity']) && $filters['min_quantity'] !== null) {
            $query->where('quantity', '>=', (float) $filters['min_quantity']);
        }

        if (isset($filters['max_quantity']) && $filters['max_quantity'] !== null) {
            $query->where('quantity', '<=', (float) $filters['max_quantity']);
        }

        if (!empty($filters['customer_id'])) {
            $partyId = (int) $filters['customer_id'];
            $query->whereHas('invoice', fn (Builder $q) => $q->where('party_id', $partyId));
        }

        // Narrow a consolidated business report to a subset of its stores.
        if (!empty($filters['store_ids']) && is_array($filters['store_ids'])) {
            $storeIds = array_map('intval', $filters['store_ids']);
            $query->whereHas('invoice', fn (Builder $q) => $q->whereIn('store_id', $storeIds));
        }

        if (!empty($filters['tag_id'])) {
            $tagId = (int) $filters['tag_id'];
            $query->whereHas('product.taggables', fn (Builder $q) => $q->where('tag_id', $tagId));
        }

        if (!empty($filters['tag_value_id'])) {
            $tagValueId = (int) $filters['tag_value_id'];
            $query->whereHas('product.taggables', fn (Builder $q) => $q->where('tag_value_id', $tagValueId));
        }

        if (!empty($filters['start_date'])) {
            $startDate = (string) $filters['start_date'];
            $query->whereHas('invoice', fn (Builder $q) => $q->whereDate('invoice_date', '>=', $startDate));
        }

        if (!empty($filters['end_date'])) {
            $endDate = (string) $filters['end_date'];
            $query->whereHas('invoice', fn (Builder $q) => $q->whereDate('invoice_date', '<=', $endDate));
        }

        $ids = $filters['ids'] ?? null;
        if (is_array($ids) && count($ids) > 0) {
            $query->whereIn('id', $ids);
        }

        $search = $filters['search'] ?? null;
        if (is_string($search) && $search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $q) use ($like) {
                $q->whereHas('product', fn (Builder $p) => $p
                    ->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like))
                    ->orWhereHas('invoice', fn (Builder $i) => $i->where('code', 'like', $like))
                    ->orWhereHas('invoice.party.customer', fn (Builder $c) => $c->where('name', 'like', $like));
            });
        }

        return $query;
    }

    private function storeBaseQuery(int $storeId): Builder
    {
        return InvoiceProduct::query()
            ->with(self::RELATIONS)
            ->whereHas('invoice', fn (Builder $q) => $q
                ->where('store_id', $storeId)
                ->where('type', InvoiceTypeEnum::SALE->value))
            ->orderByDesc('invoice_id')
            ->orderByDesc('id');
    }

    private function businessBaseQuery(int $businessId): Builder
    {
        return InvoiceProduct::query()
            ->with(array_merge(self::RELATIONS, ['invoice.store']))
            ->whereHas('invoice', fn (Builder $q) => $q
                ->where('type', InvoiceTypeEnum::SALE->value)
                ->whereHas('store', fn (Builder $s) => $s->where('business_id', $businessId)))
            ->orderByDesc('invoice_id')
            ->orderByDesc('id');
    }
}
