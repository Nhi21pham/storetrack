<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\BaseResolver;
use App\Services\CustomerService;

class CustomerResolver extends BaseResolver
{
    public function __construct(private CustomerService $customerService) {}

    public function create($_, array $args)
    {
        return $this->safe(function () use ($args) {
            $storeId    = (int) $args['store_id'];
            $businessId = (int) $args['business_id'];
            unset($args['store_id'], $args['business_id']);
            return $this->customerService->create($this->user(), $storeId, $businessId, $args);
        });
    }

    public function update($_, array $args)
    {
        return $this->safe(function () use ($args) {
            $id         = (int) $args['id'];
            $storeId    = (int) $args['store_id'];
            $businessId = (int) $args['business_id'];
            unset($args['id'], $args['store_id'], $args['business_id']);
            return $this->customerService->update($this->user(), $storeId, $businessId, $id, $args);
        });
    }

    public function delete($_, array $args): bool
    {
        return $this->safe(function () use ($args) {
            $this->customerService->delete(
                $this->user(),
                (int) $args['store_id'],
                (int) $args['business_id'],
                (int) $args['id']
            );
            return true;
        });
    }
}
