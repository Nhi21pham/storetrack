<?php

namespace App\Repositories\Report;

use App\Models\Invoice\InventoryBatch;
use App\Support\ReportExportOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reads inventory batches for the stock report. Each batch is one purchase lot,
 * so a report row carries its product, the purchase invoice that created it
 * (and thus the supplier), the purchase date, quantities, and unit cost.
 *
 * The same row shape serves two scopes: a single store, or every store in a
 * business (the consolidated owner view, where the store relation is loaded so
 * each row can name its store).
 */
class StockReportRepository
{
    private const RELATIONS = ['product.unit', 'product.taggables.tag', 'product.taggables.tagValue', 'sourceInvoice.party.supplier'];

    /** Every batch in the store (including depleted ones), for the list view. */
    public function all(int $storeId): Collection
    {
        return $this->storeBaseQuery($storeId)->get();
    }

    /** Every batch across all of the business's stores, for the consolidated list view. */
    public function allForBusiness(int $businessId): Collection
    {
        return $this->businessBaseQuery($businessId)->get();
    }

    /**
     * A filtered, ordered query of batches for a store. Used by the export job,
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
        // Out-of-stock (fully depleted) lots are hidden unless explicitly asked for.
        if (empty($filters['include_out_of_stock'])) {
            $query->where('quantity_remaining', '>', 0);
        }

        if (isset($filters['min_quantity']) && $filters['min_quantity'] !== null) {
            $query->where('quantity_remaining', '>=', (float) $filters['min_quantity']);
        }

        if (isset($filters['max_quantity']) && $filters['max_quantity'] !== null) {
            $query->where('quantity_remaining', '<=', (float) $filters['max_quantity']);
        }

        if (!empty($filters['supplier_id'])) {
            $partyId = (int) $filters['supplier_id'];
            $query->whereHas('sourceInvoice', fn (Builder $q) => $q->where('party_id', $partyId));
        }

        // Narrow a consolidated business report to a subset of its stores.
        if (!empty($filters['store_ids']) && is_array($filters['store_ids'])) {
            $storeIds = array_map('intval', $filters['store_ids']);
            $query->whereIn('store_id', $storeIds);
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
            $query->whereDate('received_at', '>=', (string) $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('received_at', '<=', (string) $filters['end_date']);
        }

        $ids = $filters['ids'] ?? null;
        if (is_array($ids) && count($ids) > 0) {
            $query->whereIn('id', $ids);
            ReportExportOrder::byIds($query, 'id', $ids);
        }

        $search = $filters['search'] ?? null;
        if (is_string($search) && $search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $q) use ($like) {
                $q->whereHas('product', fn (Builder $p) => $p
                    ->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like))
                    ->orWhereHas('sourceInvoice', fn (Builder $i) => $i->where('code', 'like', $like))
                    ->orWhereHas('sourceInvoice.party.supplier', fn (Builder $s) => $s->where('name', 'like', $like));
            });
        }

        return $query;
    }

    private function storeBaseQuery(int $storeId): Builder
    {
        return InventoryBatch::query()
            ->with(self::RELATIONS)
            ->where('store_id', $storeId)
            ->orderByDesc('received_at')
            ->orderByDesc('id');
    }

    private function businessBaseQuery(int $businessId): Builder
    {
        return InventoryBatch::query()
            ->with(array_merge(self::RELATIONS, ['store']))
            ->whereHas('store', fn (Builder $q) => $q->where('business_id', $businessId))
            ->orderBy('store_id')
            ->orderByDesc('received_at')
            ->orderByDesc('id');
    }
}
