<?php

namespace App\Repositories;

use App\Models\Tax;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class TaxRepository
{
    public function findById(int $id): ?Tax
    {
        return Tax::find($id);
    }

    public function findByNameNormalized(int $storeId, string $normalized, ?int $excludeId = null): ?Tax
    {
        $query = Tax::query()
            ->where('store_id', $storeId)
            ->where('name_normalized', $normalized);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->first();
    }

    public function create(array $data): Tax
    {
        return Tax::create($data);
    }

    public function update(Tax $tax, array $data): Tax
    {
        $tax->update($data);
        return $tax->fresh();
    }

    public function delete(Tax $tax): void
    {
        $tax->delete();
    }

    public function all(int $storeId, bool $includeInactive = false): Collection
    {
        $query = Tax::query()->where('store_id', $storeId);
        if (!$includeInactive) {
            $query->where('is_active', true);
        }
        return $query->orderByDesc('is_system')->orderBy('name')->get();
    }

    public function searchQuery(int $storeId, string $needle, bool $includeInactive = false, ?int $limit = null): Builder
    {
        $query = Tax::query()->where('store_id', $storeId);
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
