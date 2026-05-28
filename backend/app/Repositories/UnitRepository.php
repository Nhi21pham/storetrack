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

    public function all(int $storeId, bool $includeInactive = false): Collection
    {
        $query = Unit::query()->where('store_id', $storeId);
        if (!$includeInactive) {
            $query->where('is_active', true);
        }
        return $query->orderBy('name')->get();
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
