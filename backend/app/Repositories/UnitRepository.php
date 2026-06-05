<?php

namespace App\Repositories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class UnitRepository
{
    public function findById(int $id): ?Unit
    {
        return Unit::find($id);
    }

    public function findByNameNormalized(int $storeId, string $normalized, ?int $excludeId = null): ?Unit
    {
        $query = Unit::query()
            ->where('store_id', $storeId)
            ->where('name_normalized', $normalized);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->first();
    }

    public function create(array $data): Unit
    {
        return Unit::create($data);
    }

    public function update(Unit $unit, array $data): Unit
    {
        $unit->update($data);
        return $unit->fresh();
    }

    public function delete(Unit $unit): void
    {
        $unit->delete();
    }

    public function hasProducts(int $unitId): bool
    {
        return Unit::where('id', $unitId)->whereHas('products')->exists();
    }

    public function all(int $storeId, bool $includeInactive = false): Collection
    {
        $query = Unit::query()->where('store_id', $storeId);
        if (!$includeInactive) {
            $query->where('is_active', true);
        }
        return $query->orderBy('name')->get();
    }

    public function listQuery(int $storeId, ?string $search = null, ?array $ids = null): Builder
    {
        $query = Unit::query()->where('store_id', $storeId);

        if ($ids !== null && count($ids) > 0) {
            $query->whereIn('id', $ids);
        }

        if ($search !== null && $search !== '') {
            $needle = '%'.$search.'%';
            $query->where('name', 'like', $needle);
        }

        return $query->orderBy('id');
    }

    public function searchQuery(int $storeId, string $needle, bool $includeInactive = false, ?int $limit = null): Builder
    {
        $query = Unit::query()->where('store_id', $storeId);
        if (!$includeInactive) {
            $query->where('is_active', true);
        }
        $like = '%' . $needle . '%';
        $query->where('name_normalized', 'like', $like);
        $query->orderBy('name');
        if ($limit !== null) {
            $query->limit($limit);
        }
        return $query;
    }
}
