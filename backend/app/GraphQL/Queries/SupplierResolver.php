<?php

namespace App\GraphQL\Queries;

use App\GraphQL\BaseResolver;
use App\Services\SupplierService;

class SupplierResolver extends BaseResolver
{
    public function __construct(private SupplierService $supplierService) {}

    public function all($_, array $args)
    {
        return $this->safe(fn() =>
            $this->supplierService->getAll()
        );
    }

    public function findById($_, array $args)
    {
        return $this->safe(fn() =>
            $this->supplierService->getById((int) $args['id'])
        );
    }
}
