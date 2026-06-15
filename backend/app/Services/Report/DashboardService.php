<?php

namespace App\Services\Report;

use App\Models\User;
use App\Repositories\Report\DashboardRepository;
use App\Repositories\Report\TopProductsReportRepository;
use App\Services\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;

/**
 * Assembles the dashboard summary for a scope (a single store, or every store in
 * a business) and a month: this-month sales/profit with the % change vs the
 * previous month, current stock + outstanding, a 12-month sales trend ending at
 * the selected month, the month's products (all metrics, ranked client-side),
 * and — at business level — per-store performance.
 */
class DashboardService
{
    public function __construct(
        private DashboardRepository $repository,
        private TopProductsReportRepository $topProductsRepository,
        private PermissionService $permissionService,
    ) {}

    public function getReport(User $user, int $storeId, ?string $month = null): array
    {
        if ($this->permissionService->getUserRoleOnStore($user, $storeId) === null) {
            throw new AuthorizationException('You do not have access to this store.');
        }

        return $this->buildSummary($storeId, null, $month);
    }

    public function getBusinessReport(User $user, int $businessId, ?string $month = null): array
    {
        $this->permissionService->authorizeBusinessOwner($user, $businessId);

        return $this->buildSummary(null, $businessId, $month);
    }

    private function buildSummary(?int $storeId, ?int $businessId, ?string $month): array
    {
        $selStart = $this->monthStart($month);
        $selEnd = $selStart->copy()->endOfMonth();
        $prevStart = $selStart->copy()->subMonthNoOverflow()->startOfMonth();
        $prevEnd = $prevStart->copy()->endOfMonth();

        $selEndStr = $selEnd->toDateString();
        $prevEndStr = $prevEnd->toDateString();

        // Flow metrics: totals within the month vs the previous month.
        $salesNow = $this->repository->salesTotal($storeId, $businessId, $selStart->toDateString(), $selEndStr);
        $salesPrev = $this->repository->salesTotal($storeId, $businessId, $prevStart->toDateString(), $prevEndStr);
        $profitNow = $this->repository->profitTotal($storeId, $businessId, $selStart->toDateString(), $selEndStr);
        $profitPrev = $this->repository->profitTotal($storeId, $businessId, $prevStart->toDateString(), $prevEndStr);

        // Balance metrics: the level as of each month-end (so MoM is meaningful).
        $stockNow = $this->repository->stockAsOf($storeId, $businessId, $selEndStr);
        $stockPrev = $this->repository->stockAsOf($storeId, $businessId, $prevEndStr);
        $arNow = $this->repository->outstandingAsOf($storeId, $businessId, $selEndStr);
        $arPrev = $this->repository->outstandingAsOf($storeId, $businessId, $prevEndStr);

        return [
            'month'                     => $selStart->format('Y-m'),
            'total_sales'               => $salesNow,
            'total_sales_change'        => $this->pctChange($salesNow, $salesPrev),
            'total_profit'              => $profitNow,
            'total_profit_change'       => $this->pctChange($profitNow, $profitPrev),
            'products_in_stock'         => $stockNow,
            'products_in_stock_change'  => $this->pctChange($stockNow, $stockPrev),
            'outstanding'               => $arNow,
            'outstanding_change'        => $this->pctChange($arNow, $arPrev),
            'sales_trend'               => $this->trend($storeId, $businessId, $selStart, $selEnd),
            'top_products'              => $this->topProducts($storeId, $businessId, $selStart, $selEnd),
            'store_performance'         => $businessId !== null ? $this->storePerformance($businessId, $selStart, $selEnd) : [],
        ];
    }

    /** 12 monthly revenue points ending at the selected month, zero-filled. */
    private function trend(?int $storeId, ?int $businessId, Carbon $selStart, Carbon $selEnd): array
    {
        $trendStart = $selStart->copy()->subMonthsNoOverflow(11)->startOfMonth();
        $byMonth = $this->repository->monthlySales($storeId, $businessId, $trendStart->toDateString(), $selEnd->toDateString());

        $points = [];
        $cursor = $trendStart->copy();
        for ($i = 0; $i < 12; $i++) {
            $points[] = [
                'label'   => $cursor->format('M'),
                'revenue' => $byMonth[$cursor->format('Y-m')] ?? 0.0,
            ];
            $cursor->addMonthNoOverflow();
        }

        return $points;
    }

    /** Every product sold in the month with all four metrics; the panel ranks + slices client-side. */
    private function topProducts(?int $storeId, ?int $businessId, Carbon $selStart, Carbon $selEnd): array
    {
        $filters = ['start_date' => $selStart->toDateString(), 'end_date' => $selEnd->toDateString(), 'sort' => 'revenue'];

        $rows = $storeId !== null
            ? $this->topProductsRepository->listQuery($storeId, $filters)->get()
            : $this->topProductsRepository->listQueryForBusiness($businessId, $filters)->get();

        return $rows->map(fn ($row) => [
            'product_id'   => (int) $row->product_id,
            'product_name' => $row->product_name,
            'qty_sold'     => (float) $row->qty_sold,
            'revenue'      => round((float) $row->revenue, 2),
            'profit'       => round((float) $row->revenue - (float) $row->cost, 2),
            'orders'       => (int) $row->orders,
        ])->all();
    }

    private function storePerformance(int $businessId, Carbon $selStart, Carbon $selEnd): array
    {
        return $this->repository->storePerformance($businessId, $selStart->toDateString(), $selEnd->toDateString())
            ->map(fn ($row) => [
                'store_id'   => (int) $row->store_id,
                'store_name' => $row->store_name,
                'revenue'    => round((float) $row->revenue, 2),
                'profit'     => round((float) $row->revenue - (float) $row->cost, 2),
            ])->all();
    }

    /** Start of the selected month ("YYYY-MM"), or the current month when none is given. */
    private function monthStart(?string $month): Carbon
    {
        if ($month !== null && $month !== '') {
            return Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        }

        return Carbon::now()->startOfMonth();
    }

    /** Percent change vs the previous period; null when there's nothing to compare to. */
    private function pctChange(float $current, float $previous): ?float
    {
        if ($previous == 0.0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
