<?php

namespace App\Services\Invoice;

use App\Enums\ErrorCode;
use App\Exceptions\InvoiceException;
use App\Models\Invoice\InvoiceProduct;
use App\Repositories\Invoice\InventoryBatchRepository;
use App\Repositories\Invoice\InvoiceProductCostRepository;
use App\Repositories\Invoice\InvoiceRepository;
use App\Repositories\Invoice\ProductStockRepository;

/**
 * Rebuilds a product's FIFO ledger after an edit disturbs its supply or demand.
 *
 * A product is a ledger of supply (purchase batches) and demand (sale lines).
 * Editing an invoice can change either side, so its whole ledger has to be
 * re-matched: release() hands every consumed unit back to its batch and drops
 * the cost rows, and replay() re-draws every sale in date order against the
 * (now rebuilt) supply. Consumption is guarded — a sale only draws from batches
 * received on or before its own date — so replay() rejects a change that would
 * leave an earlier sale short of stock, rolling the enclosing transaction back.
 *
 * The caller runs this inside a transaction and edits the invoice's own rows/
 * batches between release() and replay().
 */
class InventoryReflowService
{
    public function __construct(
        private InventoryCostingService $costingService,
        private InventoryBatchRepository $batchRepository,
        private InvoiceProductCostRepository $costRepository,
        private ProductStockRepository $stockRepository,
        private InvoiceRepository $invoiceRepository,
    ) {}

    /** Lock every batch of the affected products so a concurrent sale can't interleave with the re-flow. */
    public function lock(int $storeId, array $productIds): void
    {
        $this->batchRepository->lockAllForProducts($storeId, $productIds);
    }

    /** Return every unit the products' sales consumed back to its batch, then drop those cost rows. */
    public function release(int $storeId, array $productIds): void
    {
        $costs = $this->costRepository->lockForProducts($productIds);

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

        $this->costRepository->deleteForProducts($productIds);
    }

    /**
     * Re-draw every sale of the products, oldest first, against the current batches.
     * A sale that can no longer be covered by stock received on or before its date
     * makes the whole edit infeasible.
     */
    public function replay(int $storeId, array $productIds): void
    {
        $lines = $this->invoiceRepository->saleLinesForProducts($storeId, $productIds);

        foreach ($lines as $line) {
            try {
                $this->costingService->consume(
                    $storeId,
                    (int) $line->product_id,
                    (string) $line->product_name,
                    (int) $line->id,
                    (float) $line->quantity,
                    $this->saleDate($line),
                );
            } catch (InvoiceException $e) {
                if ($e->getErrorCode() === ErrorCode::INSUFFICIENT_STOCK) {
                    throw new InvoiceException(
                        ErrorCode::INVOICE_EDIT_SHORTFALL,
                        "This change leaves sale {$line->invoice?->code} short of stock received on or before its date.",
                    );
                }
                throw $e;
            }
        }
    }

    private function saleDate(InvoiceProduct $line): string
    {
        return $line->invoice->invoice_date->format('Y-m-d');
    }
}
