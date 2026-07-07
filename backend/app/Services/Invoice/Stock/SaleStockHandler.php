<?php

namespace App\Services\Invoice\Stock;

use App\Enums\ErrorCode;
use App\Exceptions\InvoiceException;
use App\Models\Invoice\Invoice;
use App\Models\Invoice\InvoiceProduct;
use App\Models\Product;
use App\Repositories\CustomerRepository;
use App\Repositories\StoreRepository;
use App\Services\Invoice\InventoryCostingService;

class SaleStockHandler implements InvoiceStockHandler
{
    public function __construct(
        private InventoryCostingService $costingService,
        private CustomerRepository $customerRepository,
        private StoreRepository $storeRepository,
    ) {}

    public function assertParty(int $partyId, int $storeId): void
    {
        // Customers are business-scoped, like suppliers.
        $businessId = $this->storeRepository->businessIdFor($storeId);

        if ($businessId === null || !$this->customerRepository->existsInBusiness($partyId, $businessId)) {
            throw new InvoiceException(
                ErrorCode::INVOICE_PARTY_INVALID,
                'Customer not found in this business.'
            );
        }
    }

    /** A sale at this store links the customer to it, so it shows under the store. */
    public function linkPartyToStore(int $partyId, int $storeId): void
    {
        $this->customerRepository->attachStoreByParty($partyId, $storeId);
    }

    /** The sale price is revenue; the line draws its quantity from FIFO batches for its cost. */
    public function applyLine(
        Invoice $invoice,
        InvoiceProduct $line,
        Product $product,
        float $quantity,
        float $unitPrice,
        string $invoiceDate,
    ): void {
        $this->costingService->consume(
            (int) $invoice->store_id,
            (int) $product->id,
            (string) $product->name,
            (int) $line->id,
            $quantity,
            $invoiceDate,
        );
    }

    /**
     * A sale supplies no stock; its consumption is (re)applied when the product's
     * sales are replayed during a re-flow, so there is nothing to do here.
     */
    public function establishSupply(
        Invoice $invoice,
        InvoiceProduct $line,
        Product $product,
        float $quantity,
        float $unitPrice,
        string $invoiceDate,
    ): void {}

    /** Returns the stock this sale consumed back to its FIFO batches. */
    public function reverse(Invoice $invoice): void
    {
        $this->costingService->releaseConsumption((int) $invoice->id);
    }
}
