<?php

namespace App\Services\Invoice;

use App\Enums\ErrorCode;
use App\Exceptions\InvoiceException;
use App\Models\Invoice\InventoryBatch;
use App\Repositories\Invoice\InventoryBatchRepository;
use App\Repositories\Invoice\InvoiceProductCostRepository;
use App\Repositories\Invoice\ProductStockRepository;

class InventoryCostingService
{
    public function __construct(
        private InventoryBatchRepository $batchRepository,
        private InvoiceProductCostRepository $costRepository,
        private ProductStockRepository $stockRepository,
    ) {}

    /** A purchase line fills a new FIFO batch at its net unit cost and increases on-hand stock. */
    public function addBatch(
        int $storeId,
        int $productId,
        int $invoiceId,
        int $invoiceProductId,
        float $unitCost,
        float $quantity,
        string $receivedAt,
    ): InventoryBatch {
        $batch = $this->batchRepository->create([
            'store_id'                  => $storeId,
            'product_id'                => $productId,
            'source_invoice_id'         => $invoiceId,
            'source_invoice_product_id' => $invoiceProductId,
            'unit_cost'                 => $unitCost,
            'quantity_received'         => $quantity,
            'quantity_remaining'        => $quantity,
            'received_at'               => $receivedAt,
        ]);

        $this->stockRepository->increment($storeId, $productId, $quantity);

        return $batch;
    }

    /** Reverse a batch (decrement its remaining stock, delete it). Caller must hold a FOR UPDATE lock on $batch. */
    public function removeBatch(InventoryBatch $batch): void
    {
        $this->stockRepository->decrement(
            (int) $batch->store_id,
            (int) $batch->product_id,
            (float) $batch->quantity_remaining,
        );
        $this->batchRepository->delete($batch);
    }

    /**
     * A sale line draws its quantity from the product's FIFO batches, oldest first.
     * Each batch touched is recorded as an invoice_product_cost (the line's COGS),
     * its remaining quantity is reduced, and on-hand stock falls. Throws if the
     * open batches can't cover the requested quantity. Returns the line's COGS.
     */
    public function consume(
        int $storeId,
        int $productId,
        string $productName,
        int $invoiceProductId,
        float $quantity,
    ): float {
        $batches = $this->batchRepository->lockFifoForProduct($storeId, $productId);

        $outstanding = $quantity;
        $cogs = 0.0;

        foreach ($batches as $batch) {
            if ($outstanding <= 0) {
                break;
            }

            $available = (float) $batch->quantity_remaining;
            if ($available <= 0) {
                continue;
            }

            $taken = min($available, $outstanding);

            $this->batchRepository->decrementRemaining($batch, $taken);
            $this->costRepository->create([
                'invoice_product_id' => $invoiceProductId,
                'inventory_batch_id' => (int) $batch->id,
                'quantity'           => $taken,
                'unit_cost'          => (float) $batch->unit_cost,
            ]);

            $cogs += $taken * (float) $batch->unit_cost;
            $outstanding -= $taken;
        }

        if ($outstanding > 0.0001) {
            throw new InvoiceException(
                ErrorCode::INSUFFICIENT_STOCK,
                "Not enough stock to sell {$quantity} of '{$productName}'."
            );
        }

        $this->stockRepository->decrement($storeId, $productId, $quantity);

        return round($cogs, 2);
    }

    /**
     * Return every quantity a sale consumed back to the batch it came from, raise
     * on-hand stock, and drop the cost rows. Used when a sale is edited or deleted.
     */
    public function releaseConsumption(int $invoiceId): void
    {
        $costs = $this->costRepository->lockByInvoice($invoiceId);

        foreach ($costs as $cost) {
            $batch = $this->batchRepository->lockById((int) $cost->inventory_batch_id);
            if (!$batch) {
                continue;
            }

            $this->batchRepository->incrementRemaining($batch, (float) $cost->quantity);
            $this->stockRepository->increment(
                (int) $batch->store_id,
                (int) $batch->product_id,
                (float) $cost->quantity,
            );
        }

        $this->costRepository->deleteByInvoice($invoiceId);
    }
}
