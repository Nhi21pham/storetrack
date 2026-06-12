<?php

namespace App\GraphQL\Queries\Report;

use App\GraphQL\BaseResolver;
use App\Services\Report\StockReportService;

class StockReportResolver extends BaseResolver
{
    public function __construct(private StockReportService $stockReportService) {}

    public function all($_, array $args)
    {
        return $this->safe(fn () =>
            $this->stockReportService->getReport($this->user(), (int) $args['store_id'])
        );
    }

    public function allForBusiness($_, array $args)
    {
        return $this->safe(fn () =>
            $this->stockReportService->getBusinessReport($this->user(), (int) $args['business_id'])
        );
    }
}
