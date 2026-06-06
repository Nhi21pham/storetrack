<?php

namespace App\Services\Invoice;

use App\Models\Invoice\InventoryBatch;
use App\Repositories\Invoice\InventoryBatchRepository;
use App\Repositories\Invoice\ProductStockRepository;

class InventoryCostingService
{
    public function __construct(
        private InventoryBatchRepository $batchRepository,
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
}
