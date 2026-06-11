<?php

namespace App\GraphQL\Queries\Report;

use App\GraphQL\BaseResolver;
use App\Services\Report\SaleReportService;

class SaleReportResolver extends BaseResolver
{
    public function __construct(private SaleReportService $saleReportService) {}

    public function all($_, array $args)
    {
        return $this->safe(fn () =>
            $this->saleReportService->getReport($this->user(), (int) $args['store_id'])
        );
    }
}
