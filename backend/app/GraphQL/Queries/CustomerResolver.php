<?php

namespace App\GraphQL\Queries;

use App\GraphQL\BaseResolver;
use App\Services\CustomerService;

class CustomerResolver extends BaseResolver
{
    public function __construct(private CustomerService $customerService) {}

    public function all($_, array $args)
    {
        return $this->safe(fn() =>
            $this->customerService->getAll()
        );
    }

    public function findById($_, array $args)
    {
        return $this->safe(fn() =>
            $this->customerService->getById((int) $args['id'])
        );
    }
}
