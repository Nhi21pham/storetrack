<?php

namespace App\Services\Invoice\Stock;

use App\Enums\InvoiceTypeEnum;

class InvoiceStockHandlerFactory
{
    public function __construct(
        private PurchaseStockHandler $purchaseHandler,
        private SaleStockHandler $saleHandler,
    ) {}

    public function for(InvoiceTypeEnum $type): InvoiceStockHandler
    {
        return match ($type) {
            InvoiceTypeEnum::PURCHASE => $this->purchaseHandler,
            InvoiceTypeEnum::SALE     => $this->saleHandler,
        };
    }
}
