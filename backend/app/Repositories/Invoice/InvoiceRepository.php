<?php

namespace App\Repositories\Invoice;

use App\Models\Invoice\Invoice;
use App\Models\Invoice\InvoiceProduct;
use App\Models\Invoice\InvoiceProductTax;

class InvoiceRepository
{
    private const RELATIONS = ['party', 'creator', 'items.product', 'items.taxes'];

    public function findById(int $id): ?Invoice
    {
        return Invoice::with(self::RELATIONS)->find($id);
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
