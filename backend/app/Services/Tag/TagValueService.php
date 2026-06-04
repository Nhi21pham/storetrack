<?php

namespace App\Services\Tag;

use App\Enums\ErrorCode;
use App\Enums\PermissionEnum;
use App\Exceptions\TagException;
use App\Models\Tag\TagValue;
use App\Models\User;
use App\Repositories\Tag\TagValueRepository;
use App\Services\AuditLog\Loggers\TagValueAuditLogger;
use App\Services\PermissionService;
use App\Support\TextNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class TagValueService
{
    public function __construct(
        private TagValueRepository $tagValueRepository,
        private TagService $tagService,
        private PermissionService $permissionService,
        private TagValueAuditLogger $auditLogger,
    ) {}

    public function create(User $actor, int $tagId, array $data): TagValue
    {
        return DB::transaction(function () use ($actor, $tagId, $data) {
            $tag = $this->tagService->findTagOrFail($tagId);
            $this->permissionService->authorizeStore($actor, PermissionEnum::CREATE_TAG, (int) $tag->store_id);

            $value = trim((string) $data['value']);
            $this->assertValidValue($value);
            $valueNorm = TextNormalizer::normalize($value);

            $this->assertValueAvailable($tagId, $valueNorm);

            try {
                $tagValue = $this->tagValueRepository->create([
                    'tag_id'           => $tagId,
                    'value'            => $value,
                    'value_normalized' => $valueNorm,
                ]);
            } catch (QueryException $e) {
                $this->translateUniqueViolation($e, $value);
            }

            $this->auditLogger->tagValueCreated($actor, $tag, $tagValue);

            return $tagValue;
        });
    }

    public function update(User $actor, int $valueId, array $data): TagValue
    {
        return DB::transaction(function () use ($actor, $valueId, $data) {
            $tagValue = $this->tagService->findValueOrFail($valueId);
            $tag = $this->tagService->findTagOrFail((int) $tagValue->tag_id);
            $this->permissionService->authorizeStore($actor, PermissionEnum::UPDATE_TAG, (int) $tag->store_id);

            if (!array_key_exists('value', $data)) {
                return $tagValue;
            }

            $value = trim((string) $data['value']);
            $this->assertValidValue($value);
            $valueNorm = TextNormalizer::normalize($value);

            if ($valueNorm !== $tagValue->value_normalized) {
                $this->assertValueAvailable((int) $tag->id, $valueNorm, (int) $tagValue->id);
            }

            try {
                $tagValue = $this->tagValueRepository->update($tagValue, [
                    'value'            => $value,
                    'value_normalized' => $valueNorm,
                ]);
            } catch (QueryException $e) {
                $this->translateUniqueViolation($e, $value);
            }

            $this->auditLogger->tagValueUpdated($actor, $tag, $tagValue);

            return $tagValue;
        });
    }

    public function delete(User $actor, int $valueId): void
    {
        $tagValue = $this->tagService->findValueOrFail($valueId);
        $tag = $this->tagService->findTagOrFail((int) $tagValue->tag_id);
        $storeId = (int) $tag->store_id;
        $this->permissionService->authorizeStore($actor, PermissionEnum::DELETE_TAG_VALUE, $storeId);

        $id = (int) $tagValue->id;
        $value = (string) $tagValue->value;
        $tagName = (string) $tag->name;

        DB::transaction(function () use ($actor, $tagValue, $storeId, $tagName, $id, $value) {
            $this->tagValueRepository->delete($tagValue);
            $this->auditLogger->tagValueDeleted($actor, $storeId, $tagName, $id, $value);
        });
    }

    private function assertValidValue(string $value): void
    {
        if ($value === '') {
            throw new TagException(ErrorCode::TAG_VALUE_INVALID, 'Tag value cannot be empty.');
        }
    }

    private function assertValueAvailable(int $tagId, string $valueNorm, ?int $excludeId = null): void
    {
        $existing = $this->tagValueRepository->findByValueNormalized($tagId, $valueNorm, $excludeId);
        if ($existing !== null) {
            throw new TagException(
                ErrorCode::TAG_VALUE_TAKEN,
                "This value already exists for the tag: {$existing->value}."
            );
        }
    }

    private function translateUniqueViolation(QueryException $e, string $value): never
    {
        if (($e->errorInfo[1] ?? null) === 1062) {
            throw new TagException(
                ErrorCode::TAG_VALUE_TAKEN,
                "This value already exists for the tag: {$value}."
            );
        }
        throw $e;
    }
}
