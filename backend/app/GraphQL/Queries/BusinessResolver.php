<?php

namespace App\GraphQL\Queries;

use App\GraphQL\BaseResolver;
use App\Services\BusinessService;

class BusinessResolver extends BaseResolver
{
    public function __construct(
        private BusinessService $businessService
    ) {}

    public function myBusinesses($_, array $args)
    {
        return $this->safe(fn() =>
            $this->businessService->getUserBusinesses($this->user())
        );
    }

    public function getBusiness($_, array $args)
    {
        return $this->safe(fn() =>
            $this->businessService->getBusiness($this->user(), (int) $args['id'])
        );
    }

    public function accessibleBusinesses($_, array $args)
    {
        return $this->safe(fn() =>
            $this->businessService->getAccessibleBusinesses($this->user())
        );
    }
}