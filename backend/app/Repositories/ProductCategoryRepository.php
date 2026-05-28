<?php

namespace App\Repositories;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ProductCategoryRepository
{
    public function findById(int $id): ?ProductCategory
    {
        return ProductCategory::find($id);
    }

    public function findByCode(int $storeId, string $code, ?int $excludeId = null): ?ProductCategory
    {
        $query = ProductCategory::query()
            ->where('store_id', $storeId)
            ->where('code', $code);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->first();
    }

    public function findByNameNormalized(int $storeId, string $normalized, ?int $excludeId = null): ?ProductCategory
    {
        $query = ProductCategory::query()
            ->where('store_id', $storeId)
            ->where('name_normalized', $normalized);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->first();
    }

    public function create(array $data): ProductCategory
    {
        return ProductCategory::create($data);
    }

    public function update(ProductCategory $category, array $data): ProductCategory
    {
        $category->update($data);
        return $category->fresh();
    }

    public function delete(ProductCategory $category): void
    {
        $category->delete();
    }

    public function all(int $storeId, bool $includeInactive = false): Collection
    {
        $query = ProductCategory::query()->where('store_id', $storeId);
        if (!$includeInactive) {
            $query->where('is_active', true);
        }
        return $query->orderBy('code')->get();
    }

    public function searchQuery(int $storeId, string $needle, bool $includeInactive = false, ?int $limit = null): Builder
    {
        $query = ProductCategory::query()->where('store_id', $storeId);
        if (!$includeInactive) {
            $query->where('is_active', true);
        }
        $like = '%' . $needle . '%';
        $query->where(function ($q) use ($like) {
            $q->where('code', 'like', $like)
                ->orWhere('name_normalized', 'like', $like);
        });
        $query->orderBy('code');
        if ($limit !== null) {
            $query->limit($limit);
        }
        return $query;
    }
}
