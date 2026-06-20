<?php

namespace App\Repositories;

use App\Models\InvoiceScan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InvoiceScanRepository
{
    public function create(array $attributes): InvoiceScan
    {
        return InvoiceScan::create($attributes);
    }

    public function update(InvoiceScan $scan, array $attributes): InvoiceScan
    {
        $scan->update($attributes);

        return $scan->refresh();
    }

    public function findForStore(int $id, int $storeId): ?InvoiceScan
    {
        return InvoiceScan::where('id', $id)->where('store_id', $storeId)->first();
    }

    /**
     * Newest-first scan history for a store, optionally filtered by invoice kind
     * (type: purchase/sale), engine (scan_type), status and a created-at date
     * range. Powers the scan history board.
     */
    public function paginateForStore(
        int $storeId,
        ?string $type,
        ?string $scanType,
        ?string $status,
        ?string $startDate,
        ?string $endDate,
        int $perPage = 20
    ): LengthAwarePaginator {
        $query = InvoiceScan::query()
            ->with('user')
            ->where('store_id', $storeId)
            ->orderByDesc('id');

        if ($type !== null && $type !== '') {
            $query->where('type', $type);
        }
        if ($scanType !== null && $scanType !== '') {
            $query->where('scan_type', $scanType);
        }
        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }
        if ($startDate !== null && $startDate !== '') {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate !== null && $endDate !== '') {
            $query->whereDate('created_at', '<=', $endDate);
        }

        return $query->paginate($perPage);
    }
}
