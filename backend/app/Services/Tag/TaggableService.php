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
