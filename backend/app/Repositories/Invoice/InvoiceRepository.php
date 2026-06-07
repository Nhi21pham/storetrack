<?php

namespace App\Repositories\Invoice;

use App\Models\Invoice\Invoice;
use App\Models\Invoice\InvoiceProduct;
use App\Models\Invoice\InvoiceProductTax;
use Illuminate\Database\Eloquent\Collection;

class InvoiceRepository
{
    private const RELATIONS = ['party.supplier', 'party.customer', 'creator', 'items.product', 'items.taxes'];

    public function findById(int $id): ?Invoice
    {
        return Invoice::with(self::RELATIONS)->find($id);
    }

    public function all(int $storeId, ?string $type = null): Collection
    {
        $query = Invoice::with(['party.supplier', 'party.customer', 'creator'])->where('store_id', $storeId);
        if ($type !== null) {
            $query->where('type', $type);
        }
        return $query->orderByDesc('id')->get();
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
