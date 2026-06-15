<?php

namespace App\Repositories\Report;

use App\Enums\InvoiceTypeEnum;
use App\Models\Invoice\InvoiceProduct;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Aggregates sold product lines into one ranked row per product for the Top
 * Products report: quantity sold, revenue, exact FIFO cost (and thus profit),
 * and the number of distinct sale invoices it appeared in (# orders). One
 * GROUP BY is the single source of truth — the report list (->get()) and the
 * export (the raw Builder, streamed) both use it.
 *
 * Same shape serves a single store or every store in a business; the date
 * range and other filters are applied in SQL.
 */
class TopProductsReportRepository
{
    /** Per-line FIFO cost = Σ(slice qty × slice unit cost) for that sale line. */
    private const COST_EXPR = '(SELECT COALESCE(SUM(ipc.quantity * ipc.unit_cost), 0) '
        .'FROM invoice_product_costs ipc WHERE ipc.invoice_product_id = invoice_products.id)';

    /** Ranked products for a store, with their tags attached — for the list view. */
    public function report(int $storeId, array $filters = []): Collection
    {
        return $this->attachTags($this->storeQuery($storeId, $filters)->get());
    }

    /** Ranked products across every store in a business — owner business view. */
    public function reportForBusiness(int $businessId, array $filters = []): Collection
    {
        return $this->attachTags($this->businessQuery($businessId, $filters)->get());
    }

    /** The raw aggregated, filtered, ordered Builder for the export job to stream. */
    public function listQuery(int $storeId, array $filters = []): Builder
    {
        return $this->storeQuery($storeId, $filters);
    }

    /** As listQuery(), but scoped to every store in the business. */
    public function listQueryForBusiness(int $businessId, array $filters = []): Builder
    {
        return $this->businessQuery($businessId, $filters);
    }

    private function storeQuery(int $storeId, array $filters): Builder
    {
        return $this->applyFilters(
            $this->baseQuery()->where('invoices.store_id', $storeId),
            $filters,
        );
    }

    private function businessQuery(int $businessId, array $filters): Builder
    {
        return $this->applyFilters(
            $this->baseQuery()
                ->join('stores', 'stores.id', '=', 'invoices.store_id')
                ->where('stores.business_id', $businessId),
            $filters,
        );
    }

    /**
     * The shared spine: sold lines joined to their sale invoice + product,
     * grouped per product into the ranking metrics. Only aggregates + the
     * grouped columns are selected, so each row is one product.
     */
    private function baseQuery(): Builder
    {
        return InvoiceProduct::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_products.invoice_id')
            ->join('products', 'products.id', '=', 'invoice_products.product_id')
            ->where('invoices.type', InvoiceTypeEnum::SALE->value)
            ->groupBy('invoice_products.product_id', 'products.name', 'products.code')
            ->select('invoice_products.product_id')
            ->selectRaw('products.name as product_name')
            ->selectRaw('products.code as product_code')
            ->selectRaw('SUM(invoice_products.quantity) as qty_sold')
            ->selectRaw('SUM(invoice_products.subtotal) as revenue')
            ->selectRaw('COUNT(DISTINCT invoice_products.invoice_id) as orders')
            ->selectRaw('SUM('.self::COST_EXPR.') as cost');
    }

    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters['start_date'])) {
            $query->whereDate('invoices.invoice_date', '>=', (string) $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate('invoices.invoice_date', '<=', (string) $filters['end_date']);
        }

        // Narrow a consolidated business report to a subset of its stores.
        if (!empty($filters['store_ids']) && is_array($filters['store_ids'])) {
            $query->whereIn('invoices.store_id', array_map('intval', $filters['store_ids']));
        }

        if (!empty($filters['tag_id'])) {
            $tagId = (int) $filters['tag_id'];
            $query->whereHas('product.taggables', fn (Builder $q) => $q->where('tag_id', $tagId));
        }
        if (!empty($filters['tag_value_id'])) {
            $tagValueId = (int) $filters['tag_value_id'];
            $query->whereHas('product.taggables', fn (Builder $q) => $q->where('tag_value_id', $tagValueId));
        }

        $ids = $filters['ids'] ?? null;
        if (is_array($ids) && count($ids) > 0) {
            $query->whereIn('invoice_products.product_id', $ids);
        }

        $search = $filters['search'] ?? null;
        if (is_string($search) && $search !== '') {
            $like = '%'.$search.'%';
            $query->where(fn (Builder $q) => $q
                ->where('products.name', 'like', $like)
                ->orWhere('products.code', 'like', $like));
        }

        return $this->applySort($query, $filters['sort'] ?? null);
    }

    /** Order by the chosen ranking metric (desc), so the top product is first. */
    private function applySort(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'revenue' => $query->orderByDesc('revenue'),
            'profit'  => $query->orderByRaw('(SUM(invoice_products.subtotal) - SUM('.self::COST_EXPR.')) DESC'),
            'orders'  => $query->orderByDesc('orders'),
            default   => $query->orderByDesc('qty_sold'),
        };
    }

    /** Attach each product's structured tags (for the on-screen tag filter). */
    private function attachTags(Collection $rows): Collection
    {
        $ids = $rows->pluck('product_id')->filter()->unique()->all();
        if (empty($ids)) {
            return $rows;
        }

        $byId = Product::whereIn('id', $ids)
            ->with(['taggables.tag', 'taggables.tagValue'])
            ->get()
            ->keyBy('id');

        return $rows->each(fn (InvoiceProduct $row) => $row->setAttribute('tags', $byId->get($row->product_id)?->tags ?? []));
    }
}
