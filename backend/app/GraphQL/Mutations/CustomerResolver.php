<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\BaseResolver;
use App\Services\CustomerService;

class CustomerResolver extends BaseResolver
{
    public function __construct(private CustomerService $customerService) {}

    public function create($_, array $args)
    {
        return $this->safe(fn() =>
            $this->customerService->create($args)
        );
    }

    public function update($_, array $args)
    {
        return $this->safe(function () use ($args) {
            $id = (int) $args['id'];
            unset($args['id']);
            return $this->customerService->update($id, $args);
        });
    }

    public function delete($_, array $args): bool
    {
        return $this->safe(function () use ($args) {
            $this->customerService->delete((int) $args['id']);
            return true;
        });
    }
}
