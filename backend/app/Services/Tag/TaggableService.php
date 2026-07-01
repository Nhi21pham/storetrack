<?php

namespace App\Services\Tag;

use App\Enums\ErrorCode;
use App\Enums\PermissionEnum;
use App\Exceptions\TagException;
use App\Models\User;
use App\Repositories\Tag\TaggableRepository;
use App\Services\PermissionService;
use Illuminate\Support\Facades\DB;

class TaggableService
{
    public function __construct(
        private TaggableRepository $taggableRepository,
        private TagService $tagService,
        private PermissionService $permissionService,
    ) {}

    public function syncEntityTags(
        User $actor,
        PermissionEnum $entityPermission,
        int $storeId,
        string $taggableType,
        int $taggableId,
        array $pairs
    ): void {
        $this->permissionService->authorizeStore($actor, $entityPermission, $storeId);

        $normalized = $this->normalizePairs($storeId, $pairs);

        DB::transaction(function () use ($storeId, $taggableType, $taggableId, $normalized) {
            $this->taggableRepository->deleteForEntity($taggableType, $taggableId);
            foreach ($normalized as $pair) {
                $this->taggableRepository->attach(
                    $storeId,
                    $taggableType,
                    $taggableId,
                    $pair['tag_id'],
                    $pair['tag_value_id']
                );
            }
        });
    }

    // Additive: attaches only missing pairs to each entity (keeps existing); returns pairs newly attached, keyed by entity id.
    public function attachTagsToEntities(
        User $actor,
        PermissionEnum $entityPermission,
        int $storeId,
        string $taggableType,
        array $entityIds,
        array $pairs
    ): array {
        $this->permissionService->authorizeStore($actor, $entityPermission, $storeId);

        $entityIds = array_values(array_unique(array_map('intval', $entityIds)));
        $normalized = $this->normalizePairs($storeId, $pairs);
        if (empty($entityIds) || empty($normalized)) {
            return [];
        }

        return DB::transaction(function () use ($storeId, $taggableType, $entityIds, $normalized) {
            $existing = $this->taggableRepository
                ->pairsForEntities($taggableType, $entityIds)
                ->groupBy(fn ($row) => (int) $row->taggable_id);

            $attached = [];
            foreach ($entityIds as $entityId) {
                $present = [];
                foreach ($existing->get($entityId, collect()) as $row) {
                    $present[$this->pairKey((int) $row->tag_id, $row->tag_value_id)] = true;
                }

                foreach ($normalized as $pair) {
                    $key = $this->pairKey($pair['tag_id'], $pair['tag_value_id']);
                    if (isset($present[$key])) {
                        continue;
                    }
                    $present[$key] = true;
                    $this->taggableRepository->attach(
                        $storeId,
                        $taggableType,
                        $entityId,
                        $pair['tag_id'],
                        $pair['tag_value_id']
                    );
                    $attached[$entityId][] = $pair;
                }
            }
            return $attached;
        });
    }

    private function pairKey(int $tagId, ?int $tagValueId): string
    {
        return $tagId . ':' . ($tagValueId ?? 0);
    }

    private function normalizePairs(int $storeId, array $pairs): array
    {
        $seen = [];
        $result = [];
        foreach ($pairs as $pair) {
            $tagId = (int) ($pair['tag_id'] ?? 0);
            $tagValueId = isset($pair['tag_value_id']) && $pair['tag_value_id'] !== null
                ? (int) $pair['tag_value_id']
                : null;

            $tag = $this->tagService->findTagOrFail($tagId);
            if ((int) $tag->store_id !== $storeId) {
                throw new TagException(ErrorCode::TAG_NOT_FOUND, 'Tag does not belong to this store.');
            }

            if ($tagValueId !== null) {
                $value = $this->tagService->findValueOrFail($tagValueId);
                if ((int) $value->tag_id !== $tagId) {
                    throw new TagException(ErrorCode::TAG_VALUE_INVALID, 'Value does not belong to the given tag.');
                }
            }

            $dedupeKey = $tagId . ':' . ($tagValueId ?? 0);
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;
            $result[] = ['tag_id' => $tagId, 'tag_value_id' => $tagValueId];
        }
        return $result;
    }
}
