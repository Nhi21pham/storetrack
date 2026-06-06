<?php

namespace App\GraphQL\Queries\Invoice;

use App\GraphQL\BaseResolver;
use App\Services\Invoice\InvoiceService;

class InvoiceResolver extends BaseResolver
{
    public function __construct(private InvoiceService $invoiceService) {}

    public function findById($_, array $args)
    {
        return $this->safe(fn() =>
            $this->invoiceService->getById($this->user(), (int) $args['id'])
        );
    }
}
