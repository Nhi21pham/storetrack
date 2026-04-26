<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\BaseResolver;
use App\Services\SupplierService;

class SupplierResolver extends BaseResolver
{
    public function __construct(private SupplierService $supplierService) {}

    public function create($_, array $args)
    {
        return $this->safe(fn() =>
            $this->supplierService->create($args)
        );
    }

    public function update($_, array $args)
    {
        return $this->safe(function () use ($args) {
            $id = (int) $args['id'];
            unset($args['id']);
            return $this->supplierService->update($id, $args);
        });
    }

    public function delete($_, array $args): bool
    {
        return $this->safe(function () use ($args) {
            $this->supplierService->delete((int) $args['id']);
            return true;
        });
    }
}
