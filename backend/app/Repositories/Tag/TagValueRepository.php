<?php

namespace App\Repositories\Tag;

use App\Models\Tag\TagValue;

class TagValueRepository
{
    public function findById(int $id): ?TagValue
    {
        return TagValue::find($id);
    }

    public function findByValueNormalized(int $tagId, string $normalized, ?int $excludeId = null): ?TagValue
    {
        $query = TagValue::query()
            ->where('tag_id', $tagId)
            ->where('value_normalized', $normalized);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->first();
    }

    public function create(array $data): TagValue
    {
        return TagValue::create($data);
    }

    public function update(TagValue $value, array $data): TagValue
    {
        $value->update($data);
        return $value->fresh();
    }

    public function delete(TagValue $value): void
    {
        $value->delete();
    }
}
