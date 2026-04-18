<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\BaseResolver;
use App\Services\BusinessService;

class BusinessResolver extends BaseResolver
{
    public function __construct(private BusinessService $businessService) {}

    public function create($_, array $args)
    {
        return $this->safe(fn() =>
            $this->businessService->createBusiness($this->user(), $args)
        );
    }

    public function update($_, array $args)
    {
        return $this->safe(function () use ($args) {
            $id = (int) $args['id'];
            unset($args['id']);
            return $this->businessService->updateBusiness($this->user(), $id, $args);
        });
    }

    public function delete($_, array $args): bool
    {
        return $this->safe(function () use ($args) {
            $this->businessService->deleteBusiness($this->user(), (int) $args['id']);
            return true;
        });
    }
}