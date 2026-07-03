<?php

namespace App\Repositories\Invoice;

use App\Models\Invoice\InvoiceProductCost;
use Illuminate\Database\Eloquent\Collection;

class InvoiceProductCostRepository
{
    public function create(array $data): InvoiceProductCost
    {
        return InvoiceProductCost::create($data);
    }

    /**
     * The FIFO cost allocations made by an invoice's lines, locked for update.
     * Used to return consumed stock to its batches when a sale is edited or deleted.
     */
    public function lockByInvoice(int $invoiceId): Collection
    {
        return InvoiceProductCost::whereHas(
            'invoiceProduct',
            fn ($query) => $query->where('invoice_id', $invoiceId),
        )->lockForUpdate()->get();
    }

    public function deleteByInvoice(int $invoiceId): void
    {
        InvoiceProductCost::whereHas(
            'invoiceProduct',
            fn ($query) => $query->where('invoice_id', $invoiceId),
        )->delete();
    }

    /** Every cost slice drawn by a sale line of the given products, locked — the rows a re-flow releases. */
    public function lockForProducts(array $productIds): Collection
    {
        return InvoiceProductCost::whereHas(
            'invoiceProduct',
            fn ($query) => $query->whereIn('product_id', $productIds),
        )->lockForUpdate()->get();
    }

    public function deleteForProducts(array $productIds): void
    {
        InvoiceProductCost::whereHas(
            'invoiceProduct',
            fn ($query) => $query->whereIn('product_id', $productIds),
        )->delete();
    }
}
