<?php

namespace App\Repositories\Report;

use App\Enums\InvoiceTypeEnum;
use App\Models\Invoice\InventoryBatch;
use App\Models\Invoice\Invoice;
use App\Models\Invoice\InvoiceProduct;
use App\Models\Payment\PaymentAllocation;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reads the dashboard's aggregates for a scope (a single store, or every store
 * in a business) over a month window. Flow metrics (sales, profit, trend) take
 * a date range; balance metrics (stock, outstanding) are read **as of a date**
 * (reconstructed from purchases/sales and billings/payments) so they can be
 * compared month-over-month. Reuses the report repositories' SALE + cost patterns.
 *
 * Two scope helpers keep the SALE + store/business filter in one place:
 *   - saleInvoices(): invoice-header grain (grand_total / paid_amount).
 *   - saleLines():    line-item grain joined to its invoice (subtotal + FIFO cost,
 *                     needed for profit since cost lives on the line, not the header).
 */
class DashboardRepository
{
    /** Per-line FIFO cost = Σ(slice qty × slice unit cost) for that sale line. */
    private const COST_EXPR = '(SELECT COALESCE(SUM(ipc.quantity * ipc.unit_cost), 0) '
        .'FROM invoice_product_costs ipc WHERE ipc.invoice_product_id = invoice_products.id)';

    /** Total sales (grand_total of SALE invoices) in the range. */
    public function salesTotal(?int $storeId, ?int $businessId, string $start, string $end): float
    {
        return round((float) $this->saleInvoices($storeId, $businessId)
            ->whereBetween('invoice_date', [$start, $end])
            ->sum('grand_total'), 2);
    }

    /** Total profit (Σ line subtotal − Σ FIFO cost) over SALE lines in the range. */
    public function profitTotal(?int $storeId, ?int $businessId, string $start, string $end): float
    {
        $row = $this->saleLines($storeId, $businessId)
            ->whereBetween('invoices.invoice_date', [$start, $end])
            ->selectRaw('COALESCE(SUM(invoice_products.subtotal), 0) as revenue')
            ->selectRaw('COALESCE(SUM('.self::COST_EXPR.'), 0) as cost')
            ->first();

        return round((float) $row->revenue - (float) $row->cost, 2);
    }

    /**
     * Units in stock as of $asOf = purchased on/before − sold on/before. Equals the
     * live Σ(quantity_remaining) when $asOf is today, but lets us read a past month-end.
     */
    public function stockAsOf(?int $storeId, ?int $businessId, string $asOf): float
    {
        $purchased = InventoryBatch::query()
            ->when($storeId !== null, fn (Builder $q) => $q->where('store_id', $storeId))
            ->when($businessId !== null, fn (Builder $q) => $q->whereHas('store', fn (Builder $s) => $s->where('business_id', $businessId)))
            ->whereDate('received_at', '<=', $asOf)
            ->sum('quantity_received');

        $sold = $this->saleLines($storeId, $businessId)
            ->join('invoice_product_costs', 'invoice_product_costs.invoice_product_id', '=', 'invoice_products.id')
            ->whereDate('invoices.invoice_date', '<=', $asOf)
            ->sum('invoice_product_costs.quantity');

        return round((float) $purchased - (float) $sold, 3);
    }

    /**
     * Outstanding receivable as of $asOf = SALE billed on/before − SALE payments
     * received on/before. Equals the live open-AR balance when $asOf is today.
     */
    public function outstandingAsOf(?int $storeId, ?int $businessId, string $asOf): float
    {
        $billed = $this->saleInvoices($storeId, $businessId)
            ->whereDate('invoice_date', '<=', $asOf)
            ->sum('grand_total');

        $paid = PaymentAllocation::query()
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->join('invoices', 'invoices.id', '=', 'payment_allocations.invoice_id')
            ->where('invoices.type', InvoiceTypeEnum::SALE->value)
            ->when($storeId !== null, fn (Builder $q) => $q->where('invoices.store_id', $storeId))
            ->when($businessId !== null, fn (Builder $q) => $q->join('stores', 'stores.id', '=', 'invoices.store_id')->where('stores.business_id', $businessId))
            ->whereDate('payments.paid_at', '<=', $asOf)
            ->sum('payment_allocations.amount');

        return round((float) $billed - (float) $paid, 2);
    }

    /**
     * Monthly sales totals between $start and $end, keyed 'YYYY-MM'. The service
     * zero-fills the months that have no sales.
     *
     * @return array<string, float>
     */
    public function monthlySales(?int $storeId, ?int $businessId, string $start, string $end): array
    {
        return $this->saleInvoices($storeId, $businessId)
            ->whereBetween('invoice_date', [$start, $end])
            ->selectRaw("DATE_FORMAT(invoice_date, '%Y-%m') as ym")
            ->selectRaw('SUM(grand_total) as revenue')
            ->groupBy('ym')
            ->pluck('revenue', 'ym')
            ->map(fn ($v) => round((float) $v, 2))
            ->all();
    }

    /**
     * Per-store revenue + FIFO cost for the range — every store in the business
     * (left-joined, so stores with no sales appear with zeros). Business scope only.
     */
    public function storePerformance(int $businessId, string $start, string $end): Collection
    {
        return Store::query()
            ->where('stores.business_id', $businessId)
            ->leftJoin('invoices', fn ($join) => $join
                ->on('invoices.store_id', '=', 'stores.id')
                ->where('invoices.type', InvoiceTypeEnum::SALE->value)
                ->whereBetween('invoices.invoice_date', [$start, $end]))
            ->leftJoin('invoice_products', 'invoice_products.invoice_id', '=', 'invoices.id')
            ->groupBy('stores.id', 'stores.name')
            ->select('stores.id as store_id')
            ->selectRaw('stores.name as store_name')
            ->selectRaw('COALESCE(SUM(invoice_products.subtotal), 0) as revenue')
            ->selectRaw('COALESCE(SUM('.self::COST_EXPR.'), 0) as cost')
            ->orderByDesc('revenue')
            ->get();
    }

    /** A SALE-invoice query scoped to a store or (failing that) a business. */
    private function saleInvoices(?int $storeId, ?int $businessId): Builder
    {
        $query = Invoice::query()->where('type', InvoiceTypeEnum::SALE->value);
        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        } elseif ($businessId !== null) {
            $query->whereHas('store', fn (Builder $q) => $q->where('business_id', $businessId));
        }

        return $query;
    }

    /** A SALE invoice-line query (joined to invoices, and to stores for business scope). */
    private function saleLines(?int $storeId, ?int $businessId): Builder
    {
        $query = InvoiceProduct::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_products.invoice_id')
            ->where('invoices.type', InvoiceTypeEnum::SALE->value);

        if ($storeId !== null) {
            $query->where('invoices.store_id', $storeId);
        } elseif ($businessId !== null) {
            $query->join('stores', 'stores.id', '=', 'invoices.store_id')
                ->where('stores.business_id', $businessId);
        }

        return $query;
    }
}
