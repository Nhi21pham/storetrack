<?php

namespace App\GraphQL\Queries\Invoice;

use App\GraphQL\BaseResolver;
use App\Services\Invoice\InvoiceService;

class InvoiceResolver extends BaseResolver
{
    public function __construct(private InvoiceService $invoiceService) {}

    public function all($_, array $args)
    {
        return $this->safe(fn() =>
            $this->invoiceService->getAll(
                $this->user(),
                (int) $args['store_id'],
                $args['type'] ?? null,
            )
        );
    }

    public function findById($_, array $args)
    {
        return $this->safe(fn() =>
            $this->invoiceService->getById($this->user(), (int) $args['id'])
        );
    }

    public function productStocks($_, array $args)
    {
        return $this->safe(fn() =>
            $this->invoiceService->getStockLevels($this->user(), (int) $args['store_id'])
        );
    }

    public function inventoryBatches($_, array $args)
    {
        return $this->safe(fn() =>
            $this->invoiceService->getOpenBatches($this->user(), (int) $args['store_id'])
        );
    }
}
