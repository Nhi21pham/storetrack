<?php

namespace App\Repositories;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;

class AuditLogRepository
{
    public function storeQuery(
        int $storeId,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $objectType = null,
        ?string $action = null,
        ?string $search = null
    ): Builder {
        $query = AuditLog::query()->where('store_id', $storeId);
        $this->applyCommonFilters($query, $startDate, $endDate, $objectType, $action);
        $this->applySearch($query, $search, false);

        return $query->orderByDesc('created_at');
    }

    public function businessQuery(
        int $businessId,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $objectType = null,
        ?string $action = null,
        ?string $storeName = null,
        ?string $search = null
    ): Builder {
        $query = AuditLog::query()->where('business_id', $businessId)->with('store');
        $this->applyCommonFilters($query, $startDate, $endDate, $objectType, $action);

        if ($storeName !== null && $storeName !== '') {
            $needle = '%'.$storeName.'%';
            $query->where(function ($q) use ($needle) {
                $q->whereHas('store', fn ($s) => $s->where('name', 'like', $needle))
                    ->orWhere('metadata->store_name', 'like', $needle);
            });
        }

        $this->applySearch($query, $search, true);

        return $query->orderByDesc('created_at');
    }

    private function applyCommonFilters(
        Builder $query,
        ?string $startDate,
        ?string $endDate,
        ?string $objectType,
        ?string $action
    ): void {
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }
        if ($objectType) {
            $query->where('object_type', $objectType);
        }
        if ($action) {
            $query->where('action', $action);
        }
    }

    private function applySearch(Builder $query, ?string $search, bool $includeStoreName): void
    {
        if ($search === null || $search === '') {
            return;
        }
        $needle = '%'.$search.'%';
        $query->where(function ($q) use ($needle, $includeStoreName) {
            $q->where('message', 'like', $needle)
                ->orWhere('actor_name', 'like', $needle)
                ->orWhere('actor_email', 'like', $needle);
            if ($includeStoreName) {
                $q->orWhereHas('store', fn ($s) => $s->where('name', 'like', $needle))
                    ->orWhere('metadata->store_name', 'like', $needle);
            }
        });
    }
}
