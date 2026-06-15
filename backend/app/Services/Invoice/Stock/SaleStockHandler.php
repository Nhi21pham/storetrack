<?php

namespace App\Services\Invoice\Stock;

use App\Enums\ErrorCode;
use App\Exceptions\InvoiceException;
use App\Models\Customer;
use App\Models\Invoice\Invoice;
use App\Models\Invoice\InvoiceProduct;
use App\Models\Product;
use App\Models\Store;
use App\Repositories\CustomerRepository;
use App\Services\Invoice\InventoryCostingService;

class SaleStockHandler implements InvoiceStockHandler
{
    public function __construct(
        private InventoryCostingService $costingService,
        private CustomerRepository $customerRepository,
    ) {}

    public function assertParty(int $partyId, int $storeId): void
    {
        // Customers are business-scoped, like suppliers.
        $businessId = Store::whereKey($storeId)->value('business_id');

        $exists = Customer::query()
            ->where('party_id', $partyId)
            ->where('business_id', $businessId)
            ->exists();

        if (!$exists) {
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
        );
    }

    /** Returns the stock this sale consumed back to its FIFO batches. */
    public function reverse(Invoice $invoice): void
    {
        $this->costingService->releaseConsumption((int) $invoice->id);
    }
}
