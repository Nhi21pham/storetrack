<?php

namespace App\Services\Invoice\Stock;

use App\Enums\ErrorCode;
use App\Exceptions\InvoiceException;
use App\Models\Invoice\Invoice;
use App\Models\Invoice\InvoiceProduct;
use App\Models\Product;
use App\Models\Store;
use App\Models\Supplier;
use App\Repositories\Invoice\InventoryBatchRepository;
use App\Services\Invoice\InventoryCostingService;

class PurchaseStockHandler implements InvoiceStockHandler
{
    public function __construct(
        private InventoryCostingService $costingService,
        private InventoryBatchRepository $batchRepository,
    ) {}

    public function assertParty(int $partyId, int $storeId): void
    {
        // Suppliers are business-scoped — a supplier in the store's business can
        // be invoiced even if it isn't linked to this specific store.
        $businessId = Store::whereKey($storeId)->value('business_id');

        $exists = Supplier::query()
            ->where('party_id', $partyId)
            ->where('business_id', $businessId)
            ->exists();

        if (!$exists) {
            throw new InvoiceException(
                ErrorCode::INVOICE_PARTY_INVALID,
                'Supplier not found in this business.'
            );
        }
    }

    /** The purchase price is the net unit cost; it fills a FIFO batch and raises stock. */
    public function applyLine(
        Invoice $invoice,
        InvoiceProduct $line,
        Product $product,
        float $quantity,
        float $unitPrice,
        string $invoiceDate,
    ): void {
        $this->costingService->addBatch(
            (int) $invoice->store_id,
            (int) $product->id,
            (int) $invoice->id,
            (int) $line->id,
            $unitPrice,
            $quantity,
            $invoiceDate,
        );
    }

    /** Removes the batches + stock this purchase created. Blocks if any has been sold. */
    public function reverse(Invoice $invoice): void
    {
        $batches = $this->batchRepository->lockBySourceInvoice((int) $invoice->id);

        foreach ($batches as $batch) {
            if ((float) $batch->quantity_remaining < (float) $batch->quantity_received) {
                throw new InvoiceException(
                    ErrorCode::INVOICE_STOCK_CONSUMED,
                    'Some items from this invoice have already been sold, so it can no longer be edited or deleted.'
                );
            }
        }

        foreach ($batches as $batch) {
            $this->costingService->removeBatch($batch);
        }
    }
}
