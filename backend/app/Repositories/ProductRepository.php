<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository
{
    public function findById(int $id): ?Product
    {
        return Product::with(['unit', 'category'])->find($id);
    }

    public function findByNameNormalized(int $storeId, string $normalized, ?int $excludeId = null): ?Product
    {
        $query = Product::query()
            ->where('store_id', $storeId)
            ->where('name_normalized', $normalized);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->first();
    }

    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);
        return $product->fresh(['unit', 'category']);
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    public function all(int $storeId, bool $includeInactive = false): Collection
    {
        $query = Product::query()->with(['unit', 'category'])->where('store_id', $storeId);
        if (!$includeInactive) {
            $query->where('is_active', true);
        }
        return $query->orderByDesc('id')->get();
    }

    public function searchQuery(int $storeId, string $needle, bool $includeInactive = false, ?int $limit = null): Builder
    {
        $query = Product::query()->with(['unit', 'category'])->where('store_id', $storeId);
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

    public function existsForUnit(int $unitId): bool
    {
        return Product::where('unit_id', $unitId)->exists();
    }

    public function existsForCategory(int $categoryId): bool
    {
        return Product::where('product_category_id', $categoryId)->exists();
    }
}
