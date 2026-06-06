<?php

namespace App\GraphQL\Mutations\Invoice;

use App\GraphQL\BaseResolver;
use App\Services\Invoice\InvoiceService;

class InvoiceResolver extends BaseResolver
{
    public function __construct(private InvoiceService $invoiceService) {}

    public function createPurchase($_, array $args)
    {
        return $this->safe(function () use ($args) {
            $storeId = (int) $args['store_id'];
            unset($args['store_id']);
            return $this->invoiceService->createPurchase($this->user(), $storeId, $args);
        });
    }
}
