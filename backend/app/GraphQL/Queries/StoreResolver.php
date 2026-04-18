<?php

namespace App\GraphQL\Queries;

use App\GraphQL\BaseResolver;
use App\Services\StoreService;

class StoreResolver extends BaseResolver
{
    public function __construct(
        private StoreService $storeService
    ) {}

    public function myStores($_, array $args)
    {
        return $this->safe(fn() =>
            $this->storeService->getUserStores($this->user())
        );
    }

    public function getStore($_, array $args)
    {
        return $this->safe(fn() =>
            $this->storeService->getStore($this->user(), (int) $args['id'])
        );
    }

    public function accessibleStores($_, array $args)
    {
        return $this->safe(fn() =>
            $this->storeService->getAccessibleStores($this->user())
        );
    }
}