<?php

namespace App\Repositories\Report;

use App\Enums\InvoiceTypeEnum;
use App\Models\Invoice\InvoiceProductCost;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reads sold batch-slices for the sale report. Each row is one
 * invoice_product_cost: the quantity a single sale line drew from a single FIFO
 * batch, carrying the sale line's price and the batch it came from. A sale line
 * spanning two batches yields two rows; a batch sold across three invoices
 * yields three.
 *
 * The same row shape serves two scopes: a single store, or every store in a
 * business (the consolidated owner view, where the sale invoice's store is
 * loaded so each row can name its store).
 */
class SaleReportRepository
{
    private const RELATIONS = [
        'invoiceProduct.product.taggables.tag',
        'invoiceProduct.product.taggables.tagValue',
        'invoiceProduct.invoice.party.customer',
        'batch.sourceInvoice',
    ];

    /** Every sold batch-slice in the store, for the list view. */
    public function all(int $storeId): Collection
    {
        return $this->storeBaseQuery($storeId)->get();
    }

    /** Every sold batch-slice across all of the business's stores, for the consolidated list view. */
    public function allForBusiness(int $businessId): Collection
    {
        return $this->businessBaseQuery($businessId)->get();
    }

    /**
     * A filtered, ordered query of sold batch-slices for a store. Used by the
     * export job, which streams it in chunks, so this returns a Builder.
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
            $query->where('invoice_product_costs.quantity', '>=', (float) $filters['min_quantity']);
        }

        if (isset($filters['max_quantity']) && $filters['max_quantity'] !== null) {
            $query->where('invoice_product_costs.quantity', '<=', (float) $filters['max_quantity']);
        }

        if (!empty($filters['customer_id'])) {
            $query->where('invoices.party_id', (int) $filters['customer_id']);
        }

        // Narrow a consolidated business report to a subset of its stores.
        if (!empty($filters['store_ids']) && is_array($filters['store_ids'])) {
            $storeIds = array_map('intval', $filters['store_ids']);
            $query->whereIn('invoices.store_id', $storeIds);
        }

        if (!empty($filters['tag_id'])) {
            $tagId = (int) $filters['tag_id'];
            $query->whereHas('invoiceProduct.product.taggables', fn (Builder $q) => $q->where('tag_id', $tagId));
        }

        if (!empty($filters['tag_value_id'])) {
            $tagValueId = (int) $filters['tag_value_id'];
            $query->whereHas('invoiceProduct.product.taggables', fn (Builder $q) => $q->where('tag_value_id', $tagValueId));
        }

        // The date filter ranges over the sale date.
        if (!empty($filters['start_date'])) {
            $query->whereDate('invoices.invoice_date', '>=', (string) $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('invoices.invoice_date', '<=', (string) $filters['end_date']);
        }

        $ids = $filters['ids'] ?? null;
        if (is_array($ids) && count($ids) > 0) {
            $query->whereIn('invoice_product_costs.id', $ids);
        }

        $search = $filters['search'] ?? null;
        if (is_string($search) && $search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $q) use ($like) {
                $q->whereHas('invoiceProduct.product', fn (Builder $p) => $p
                    ->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like))
                    ->orWhereHas('invoiceProduct.invoice', fn (Builder $i) => $i->where('code', 'like', $like))
                    ->orWhereHas('invoiceProduct.invoice.party.customer', fn (Builder $c) => $c->where('name', 'like', $like));
            });
        }

        return $query;
    }

    private function storeBaseQuery(int $storeId): Builder
    {
        return $this->baseQuery()
            ->where('invoices.store_id', $storeId)
            ->orderByDesc('invoices.id')
            ->orderByDesc('invoice_product_costs.id');
    }

    private function businessBaseQuery(int $businessId): Builder
    {
        return $this->baseQuery()
            ->join('stores', 'stores.id', '=', 'invoices.store_id')
            ->where('stores.business_id', $businessId)
            ->with(['invoiceProduct.invoice.store'])
            ->orderBy('invoices.store_id')
            ->orderByDesc('invoices.id')
            ->orderByDesc('invoice_product_costs.id');
    }

    /**
     * The shared spine: every cost row is joined up to its sale line and sale
     * invoice (so we can scope/sort/filter by the sale), restricted to sales.
     * Only invoice_product_costs.* is selected so the model hydrates cleanly.
     */
    private function baseQuery(): Builder
    {
        return InvoiceProductCost::query()
            ->select('invoice_product_costs.*')
            ->join('invoice_products', 'invoice_products.id', '=', 'invoice_product_costs.invoice_product_id')
            ->join('invoices', 'invoices.id', '=', 'invoice_products.invoice_id')
            ->where('invoices.type', InvoiceTypeEnum::SALE->value)
            ->with(self::RELATIONS);
    }
}
