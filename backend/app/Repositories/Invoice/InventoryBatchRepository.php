<?php

namespace App\Repositories\Invoice;

use App\Models\Invoice\InventoryBatch;
use Illuminate\Database\Eloquent\Collection;

class InventoryBatchRepository
{
    public function create(array $data): InventoryBatch
    {
        return InventoryBatch::create($data);
    }

    /** All batches a given invoice produced, locked for update (for reversal). */
    public function lockBySourceInvoice(int $invoiceId): Collection
    {
        return InventoryBatch::where('source_invoice_id', $invoiceId)
            ->lockForUpdate()
            ->get();
    }

    /** Open batches across a store in FIFO order — read-only, for cost previews. */
    public function openForStore(int $storeId): Collection
    {
        return InventoryBatch::where('store_id', $storeId)
            ->where('quantity_remaining', '>', 0)
            ->with('sourceInvoice:id,invoice_date')
            ->orderBy('received_at')
            ->orderBy('id')
            ->get(['id', 'product_id', 'source_invoice_id', 'unit_cost', 'quantity_remaining', 'received_at']);
    }

    /**
     * Open batches for a product in FIFO order (oldest received first, id as
     * tie-breaker), locked for update so a concurrent sale can't double-spend them.
     * Only batches received on or before the sale date are eligible — a sale can't
     * draw stock it hadn't received yet.
     */
    public function lockFifoForProduct(int $storeId, int $productId, string $saleDate): Collection
    {
        return InventoryBatch::where('store_id', $storeId)
            ->where('product_id', $productId)
            ->where('quantity_remaining', '>', 0)
            ->whereDate('received_at', '<=', $saleDate)
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /** Every batch of the given products in a store, locked — serialises a re-flow against concurrent sales. */
    public function lockAllForProducts(int $storeId, array $productIds): Collection
    {
        return InventoryBatch::where('store_id', $storeId)
            ->whereIn('product_id', $productIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    public function lockById(int $id): ?InventoryBatch
    {
        return InventoryBatch::whereKey($id)->lockForUpdate()->first();
    }

    public function decrementRemaining(InventoryBatch $batch, float $quantity): void
    {
        $batch->decrement('quantity_remaining', $quantity);
    }

    public function incrementRemaining(InventoryBatch $batch, float $quantity): void
    {
        $batch->increment('quantity_remaining', $quantity);
    }

    public function delete(InventoryBatch $batch): void
    {
        $batch->delete();
    }
}
