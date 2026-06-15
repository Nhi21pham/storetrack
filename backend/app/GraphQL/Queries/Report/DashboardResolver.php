<?php

namespace App\GraphQL\Queries\Report;

use App\GraphQL\BaseResolver;
use App\Services\Report\DashboardService;

class DashboardResolver extends BaseResolver
{
    public function __construct(private DashboardService $dashboardService) {}

    public function store($_, array $args)
    {
        return $this->safe(fn () =>
            $this->dashboardService->getReport($this->user(), (int) $args['store_id'], $args['month'] ?? null)
        );
    }

    public function business($_, array $args)
    {
        return $this->safe(fn () =>
            $this->dashboardService->getBusinessReport($this->user(), (int) $args['business_id'], $args['month'] ?? null)
        );
    }
}
