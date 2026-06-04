<?php

namespace App\Services\Tag;

use App\Enums\ErrorCode;
use App\Enums\PermissionEnum;
use App\Exceptions\TagException;
use App\Models\Tag\Tag;
use App\Models\Tag\TagValue;
use App\Models\User;
use App\Repositories\Tag\TagRepository;
use App\Repositories\Tag\TagValueRepository;
use App\Services\AuditLogService;
use App\Services\PermissionService;
use App\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class TagService
{
    public function __construct(
        private TagRepository $tagRepository,
        private TagValueRepository $tagValueRepository,
        private PermissionService $permissionService,
        private AuditLogService $auditLogService,
    ) {}

    public function getAll(User $user, int $storeId): Collection
    {
        $this->authorizeView($user, $storeId);
        return $this->tagRepository->all($storeId);
    }

    public function search(User $user, int $storeId, string $query, int $limit = 10): Collection
    {
        $this->authorizeView($user, $storeId);
        $needle = TextNormalizer::normalize($query);
        if ($needle === '') {
            return $this->tagRepository->all($storeId)->take($limit);
        }
        return $this->tagRepository->searchQuery($storeId, $needle, $limit)->get();
    }

    public function getById(User $user, int $id): Tag
    {
        $tag = $this->findTagOrFail($id);
        $this->authorizeView($user, (int) $tag->store_id);
        return $tag;
    }

    public function createKey(User $actor, int $storeId, array $data): Tag
    {
        $this->permissionService->authorizeStore($actor, PermissionEnum::CREATE_TAG, $storeId);

        return DB::transaction(function () use ($actor, $storeId, $data) {
            $name = trim((string) $data['name']);
            $description = isset($data['description']) ? (string) $data['description'] : null;
            $nameNorm = TextNormalizer::normalize($name);

            $this->assertNameAvailable($storeId, $nameNorm);

            try {
                $tag = $this->tagRepository->create([
                    'store_id'        => $storeId,
                    'name'            => $name,
                    'name_normalized' => $nameNorm,
                    'description'     => ($description === null || $description === '') ? null : $description,
                ]);
            } catch (QueryException $e) {
                $this->translateUniqueViolation($e, $name);
            }

            $this->auditLogService->tagCreated($actor, $tag);

            return $tag->fresh('values');
        });
    }

    public function updateKey(User $actor, int $id, array $data): Tag
    {
        return DB::transaction(function () use ($actor, $id, $data) {
            $tag = $this->findTagOrFail($id);
            $storeId = (int) $tag->store_id;
            $this->permissionService->authorizeStore($actor, PermissionEnum::UPDATE_TAG, $storeId);

            $patch = [];
            $renamedTo = null;

            if (array_key_exists('name', $data)) {
                $name = trim((string) $data['name']);
                $nameNorm = TextNormalizer::normalize($name);
                if ($nameNorm !== $tag->name_normalized) {
                    $this->assertNameAvailable($storeId, $nameNorm, (int) $tag->id);
                }
                $patch['name'] = $name;
                $patch['name_normalized'] = $nameNorm;
                $renamedTo = $name;
            }

            if (array_key_exists('description', $data)) {
                $description = $data['description'];
                $patch['description'] = ($description === null || $description === '') ? null : (string) $description;
            }

            if (empty($patch)) {
                return $tag;
            }

            try {
                $tag = $this->tagRepository->update($tag, $patch);
            } catch (QueryException $e) {
                $this->translateUniqueViolation($e, $renamedTo ?? $tag->name);
            }

            $this->auditLogService->tagUpdated($actor, $tag);

            return $tag;
        });
    }

    public function deleteKey(User $actor, int $id): void
    {
        $tag = $this->findTagOrFail($id);
        $storeId = (int) $tag->store_id;
        $this->permissionService->authorizeStore($actor, PermissionEnum::DELETE_TAG_KEY, $storeId);

        $tagId = (int) $tag->id;
        $name = (string) $tag->name;

        DB::transaction(function () use ($actor, $tag, $tagId, $name, $storeId) {
            $this->tagRepository->delete($tag);
            $this->auditLogService->tagKeyDeleted($actor, $tagId, $name, $storeId);
        });
    }

    public function findTagOrFail(int $id): Tag
    {
        $tag = $this->tagRepository->findById($id);
        if (!$tag) {
            throw new TagException(ErrorCode::TAG_NOT_FOUND, 'Tag not found.');
        }
        return $tag;
    }

    public function findValueOrFail(int $id): TagValue
    {
        $value = $this->tagValueRepository->findById($id);
        if (!$value) {
            throw new TagException(ErrorCode::TAG_VALUE_NOT_FOUND, 'Tag value not found.');
        }
        return $value;
    }

    private function authorizeView(User $user, int $storeId): void
    {
        $this->permissionService->authorizeStore($user, PermissionEnum::UPDATE_TAG, $storeId);
    }

    private function assertNameAvailable(int $storeId, string $nameNorm, ?int $excludeId = null): void
    {
        $existing = $this->tagRepository->findByNameNormalized($storeId, $nameNorm, $excludeId);
        if ($existing !== null) {
            throw new TagException(
                ErrorCode::TAG_NAME_TAKEN,
                "A tag with this name already exists: {$existing->name}."
            );
        }
    }

    private function translateUniqueViolation(QueryException $e, string $name): never
    {
        if (($e->errorInfo[1] ?? null) === 1062) {
            throw new TagException(
                ErrorCode::TAG_NAME_TAKEN,
                "A tag with this name already exists: {$name}."
            );
        }
        throw $e;
    }
}
