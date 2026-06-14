<?php

namespace App\GraphQL\Queries\Report;

use App\GraphQL\BaseResolver;
use App\Services\Report\DebtReportService;

class DebtReportResolver extends BaseResolver
{
    public function __construct(private DebtReportService $debtReportService) {}

    public function receivablesForStore($_, array $args)
    {
        return $this->safe(fn () =>
            $this->debtReportService->getReceivablesReport($this->user(), (int) $args['store_id'])
        );
    }

    public function receivablesForBusiness($_, array $args)
    {
        return $this->safe(fn () =>
            $this->debtReportService->getReceivablesReportByBusiness($this->user(), (int) $args['business_id'])
        );
    }

    public function payablesForStore($_, array $args)
    {
        return $this->safe(fn () =>
            $this->debtReportService->getPayablesReport($this->user(), (int) $args['store_id'])
        );
    }

    public function payablesForBusiness($_, array $args)
    {
        return $this->safe(fn () =>
            $this->debtReportService->getPayablesReportByBusiness($this->user(), (int) $args['business_id'])
        );
    }
}
