<?php

namespace App\Repositories\Tag;

use App\Models\Tag\Tag;
use App\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class TagRepository
{
    public function findById(int $id): ?Tag
    {
        return Tag::with('values')->find($id);
    }

    public function findByNameNormalized(int $storeId, string $normalized, ?int $excludeId = null): ?Tag
    {
        $query = Tag::query()
            ->where('store_id', $storeId)
            ->where('name_normalized', $normalized);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->first();
    }

    public function create(array $data): Tag
    {
        return Tag::create($data);
    }

    public function update(Tag $tag, array $data): Tag
    {
        $tag->update($data);
        return $tag->fresh('values');
    }

    public function delete(Tag $tag): void
    {
        $tag->delete();
    }

    public function all(int $storeId): Collection
    {
        return Tag::query()
            ->with('values')
            ->where('store_id', $storeId)
            ->orderByDesc('id')
            ->get();
    }

    public function listQuery(int $storeId, ?string $search = null, ?array $ids = null): Builder
    {
        $query = Tag::query()->with('values')->where('store_id', $storeId);

        if ($ids !== null && count($ids) > 0) {
            $query->whereIn('id', $ids);
        }

        if ($search !== null && $search !== '') {
            $needle = TextNormalizer::normalize($search);
            if ($needle !== '') {
                $like = '%'.$needle.'%';
                $query->where(function (Builder $q) use ($like) {
                    $q->where('name_normalized', 'like', $like)
                        ->orWhereHas('values', function (Builder $valueQuery) use ($like) {
                            $valueQuery->where('value_normalized', 'like', $like);
                        });
                });
            }
        }

        return $query->orderBy('id');
    }

    public function searchQuery(int $storeId, string $needle, ?int $limit = null): Builder
    {
        $like = '%' . $needle . '%';
        $query = Tag::query()
            ->with('values')
            ->where('store_id', $storeId)
            ->where(function (Builder $q) use ($like) {
                $q->where('name_normalized', 'like', $like)
                    ->orWhereHas('values', function (Builder $valueQuery) use ($like) {
                        $valueQuery->where('value_normalized', 'like', $like);
                    });
            })
            ->orderBy('name');
        if ($limit !== null) {
            $query->limit($limit);
        }
        return $query;
    }
}
