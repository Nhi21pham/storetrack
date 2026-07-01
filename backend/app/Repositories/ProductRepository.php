<?php

namespace App\Repositories;

use App\Models\Product;
use App\Support\TextNormalizer;
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

    /** Match a product in the store by exact code/SKU, for invoice extraction. */
    public function findByCode(int $storeId, string $code): ?Product
    {
        return Product::query()
            ->where('store_id', $storeId)
            ->where('code', $code)
            ->first();
    }

    public function create(array $data): Product
    {
        return Product::create($data);
    }

    /**
     * Normalized names of every product in the store (active or not), for
     * batched duplicate detection during import. Matches the
     * (store_id, name_normalized) unique index, so inactive products count as
     * taken too.
     *
     * @return string[]
     */
    public function allNormalizedNames(int $storeId): array
    {
        return Product::query()
            ->where('store_id', $storeId)
            ->pluck('name_normalized')
            ->all();
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
        $query = Product::query()->with(['unit', 'category', 'taggables.tag', 'taggables.tagValue'])->where('store_id', $storeId);
        if (!$includeInactive) {
            $query->where('is_active', true);
        }
        return $query->orderByDesc('id')->get();
    }

    public function listQuery(int $storeId, array $filters = []): Builder
    {
        $query = Product::query()
            ->with(['unit', 'category', 'taggables.tag', 'taggables.tagValue'])
            ->where('store_id', $storeId);

        $ids = $filters['ids'] ?? null;
        if (is_array($ids) && count($ids) > 0) {
            $query->whereIn('id', $ids);
        }

        $status = $filters['status'] ?? null;
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if (!empty($filters['unit_id'])) {
            $query->where('unit_id', (int) $filters['unit_id']);
        }

        if (!empty($filters['category_id'])) {
            $query->where('product_category_id', (int) $filters['category_id']);
        }

        if (!empty($filters['tag_ids']) && is_array($filters['tag_ids'])) {
            foreach ($filters['tag_ids'] as $tagId) {
                $tagId = (int) $tagId;
                $query->whereHas('taggables', fn ($q) => $q->where('tag_id', $tagId));
            }
        }

        if (!empty($filters['tag_value_ids']) && is_array($filters['tag_value_ids'])) {
            foreach ($filters['tag_value_ids'] as $tagValueId) {
                $tagValueId = (int) $tagValueId;
                $query->whereHas('taggables', fn ($q) => $q->where('tag_value_id', $tagValueId));
            }
        }

        $search = $filters['search'] ?? null;
        if (is_string($search) && $search !== '') {
            $needle = TextNormalizer::normalize($search);
            if ($needle !== '') {
                $like = '%'.$needle.'%';
                $query->where(function (Builder $q) use ($like) {
                    $q->where('code', 'like', $like)
                        ->orWhere('name_normalized', 'like', $like)
                        ->orWhereHas('unit', fn ($u) => $u->where('name_normalized', 'like', $like))
                        ->orWhereHas('category', fn ($c) => $c->where('name_normalized', 'like', $like))
                        ->orWhereHas('taggables', function ($tq) use ($like) {
                            $tq->whereHas('tag', fn ($t) => $t->where('name_normalized', 'like', $like))
                                ->orWhereHas('tagValue', fn ($v) => $v->where('value_normalized', 'like', $like));
                        });
                });
            }
        }

        return $query->orderBy('id');
    }

    public function searchQuery(int $storeId, string $needle, bool $includeInactive = false, ?int $limit = null): Builder
    {
        $query = Product::query()->with(['unit', 'category', 'taggables.tag', 'taggables.tagValue'])->where('store_id', $storeId);
        if (!$includeInactive) {
            $query->where('is_active', true);
        }
        $like = '%' . $needle . '%';
        $query->where(function ($q) use ($like) {
            $q->where('code', 'like', $like)
                ->orWhere('name_normalized', 'like', $like)
                ->orWhereHas('taggables', function ($tq) use ($like) {
                    $tq->whereHas('tag', fn ($t) => $t->where('name_normalized', 'like', $like))
                        ->orWhereHas('tagValue', fn ($v) => $v->where('value_normalized', 'like', $like));
                });
        });
        $query->orderBy('code');
        if ($limit !== null) {
            $query->limit($limit);
        }
        return $query;
    }

    public function byIds(int $storeId, array $ids, bool $includeInactive = false): Collection
    {
        if (empty($ids)) {
            return new Collection();
        }
        $query = Product::query()
            ->with(['unit', 'category', 'taggables.tag', 'taggables.tagValue'])
            ->where('store_id', $storeId)
            ->whereIn('id', $ids);
        if (!$includeInactive) {
            $query->where('is_active', true);
        }
        return $query->orderByDesc('id')->get();
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
