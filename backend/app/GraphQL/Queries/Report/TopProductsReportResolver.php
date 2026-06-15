<?php

namespace App\GraphQL\Queries\Report;

use App\GraphQL\BaseResolver;
use App\Services\Report\TopProductsReportService;

class TopProductsReportResolver extends BaseResolver
{
    public function __construct(private TopProductsReportService $topProductsReportService) {}

    public function all($_, array $args)
    {
        return $this->safe(fn () =>
            $this->topProductsReportService->getReport($this->user(), (int) $args['store_id'], $this->filters($args))
        );
    }

    public function allForBusiness($_, array $args)
    {
        return $this->safe(fn () =>
            $this->topProductsReportService->getBusinessReport($this->user(), (int) $args['business_id'], $this->filters($args))
        );
    }

    /** Only the date range scopes the server-side aggregation; the rest is client-side. */
    private function filters(array $args): array
    {
        return array_filter([
            'start_date' => $args['start_date'] ?? null,
            'end_date'   => $args['end_date'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
