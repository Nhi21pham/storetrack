<?php

namespace App\Repositories;

use App\Models\ProductCategory;
use App\Support\TextNormalizer;
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
        return $query->orderByDesc('id')->get();
    }

    /**
     * Filtered, ordered query used by the export job.
     *
     * @param  int[]|null  $ids  restrict to these category ids (selection export)
     * @param  string|null  $status  'active' | 'inactive' | null (all)
     */
    public function listQuery(int $storeId, ?string $search = null, ?array $ids = null, ?string $status = null): Builder
    {
        $query = ProductCategory::query()->where('store_id', $storeId);

        if ($ids !== null && count($ids) > 0) {
            $query->whereIn('id', $ids);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($search !== null && $search !== '') {
            $needle = TextNormalizer::normalize($search);
            if ($needle !== '') {
                $like = '%' . $needle . '%';
                $query->where(function (Builder $q) use ($like) {
                    $q->where('name_normalized', 'like', $like)
                        ->orWhere('code', 'like', $like);
                });
            }
        }

        return $query->orderBy('id');
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
