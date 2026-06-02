<?php

namespace App\Repositories\Tag;

use App\Models\Tag\Taggable;
use Illuminate\Support\Collection;

class TaggableRepository
{
    public function attach(int $storeId, string $taggableType, int $taggableId, int $tagId, ?int $tagValueId): Taggable
    {
        return Taggable::create([
            'store_id'      => $storeId,
            'tag_id'        => $tagId,
            'tag_value_id'  => $tagValueId,
            'taggable_type' => $taggableType,
            'taggable_id'   => $taggableId,
        ]);
    }

    public function detach(string $taggableType, int $taggableId, int $tagId, ?int $tagValueId): void
    {
        Taggable::query()
            ->where('taggable_type', $taggableType)
            ->where('taggable_id', $taggableId)
            ->where('tag_id', $tagId)
            ->where('tag_value_id', $tagValueId)
            ->delete();
    }

    public function deleteForEntity(string $taggableType, int $taggableId): void
    {
        Taggable::query()
            ->where('taggable_type', $taggableType)
            ->where('taggable_id', $taggableId)
            ->delete();
    }

    public function pairsForEntity(string $taggableType, int $taggableId): Collection
    {
        return Taggable::query()
            ->where('taggable_type', $taggableType)
            ->where('taggable_id', $taggableId)
            ->get(['tag_id', 'tag_value_id']);
    }

    public function entityIdsByTag(int $storeId, string $taggableType, int $tagId, ?int $tagValueId = null): array
    {
        $query = Taggable::query()
            ->where('store_id', $storeId)
            ->where('taggable_type', $taggableType)
            ->where('tag_id', $tagId);
        if ($tagValueId !== null) {
            $query->where('tag_value_id', $tagValueId);
        }
        return $query->pluck('taggable_id')->unique()->values()->all();
    }
}
