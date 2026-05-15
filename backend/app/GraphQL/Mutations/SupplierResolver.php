<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\BaseResolver;
use App\Services\SupplierService;

class SupplierResolver extends BaseResolver
{
    public function __construct(private SupplierService $supplierService) {}

    public function create($_, array $args)
    {
        return $this->safe(function () use ($args) {
            $storeId    = (int) $args['store_id'];
            $businessId = (int) $args['business_id'];
            unset($args['store_id'], $args['business_id']);
            return $this->supplierService->create($this->user(), $storeId, $businessId, $args);
        });
    }

    public function update($_, array $args)
    {
        return $this->safe(function () use ($args) {
            $id         = (int) $args['id'];
            $storeId    = (int) $args['store_id'];
            $businessId = (int) $args['business_id'];
            unset($args['id'], $args['store_id'], $args['business_id']);
            return $this->supplierService->update($this->user(), $storeId, $businessId, $id, $args);
        });
    }

    public function delete($_, array $args): bool
    {
        return $this->safe(function () use ($args) {
            $this->supplierService->delete(
                $this->user(),
                (int) $args['store_id'],
                (int) $args['business_id'],
                (int) $args['id']
            );
            return true;
        });
    }

    public function deleteMany($_, array $args): int
    {
        return $this->safe(function () use ($args) {
            $storeId = isset($args['store_id']) ? (int) $args['store_id'] : null;
            $ids = array_map('intval', $args['ids'] ?? []);
            return $this->supplierService->deleteMany(
                $this->user(),
                (int) $args['business_id'],
                $storeId,
                $ids
            );
        });
    }
}
