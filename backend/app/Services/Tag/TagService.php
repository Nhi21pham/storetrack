<?php

namespace App\Services\Tag;

use App\Enums\ErrorCode;
use App\Enums\ExportScope;
use App\Enums\PermissionEnum;
use App\Exceptions\TagException;
use App\Exports\TagExport;
use App\Jobs\Exports\ExportTagJob;
use App\Models\Export;
use App\Models\Store;
use App\Models\Tag\Tag;
use App\Models\Tag\TagValue;
use App\Models\User;
use App\Repositories\Tag\TagRepository;
use App\Repositories\Tag\TagValueRepository;
use App\Services\AuditLog\Loggers\TagAuditLogger;
use App\Services\AuditLog\Loggers\TagValueAuditLogger;
use App\Services\ExportService;
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
        private TagAuditLogger $auditLogger,
        private TagValueAuditLogger $valueAuditLogger,
        private ExportService $exportService,
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

            $this->auditLogger->tagCreated($actor, $tag);

            if (!empty($data['values'])) {
                $this->attachValues($actor, $tag, $data['values']);
            }

            return $tag->fresh('values');
        });
    }

    private function attachValues(User $actor, Tag $tag, array $values): void
    {
        $seen = [];

        foreach ($values as $rawValue) {
            $value = trim((string) $rawValue);
            if ($value === '') {
                continue;
            }

            if (mb_strlen($value) > 100) {
                throw new TagException(
                    ErrorCode::TAG_VALUE_INVALID,
                    "Tag value must be at most 100 characters: {$value}."
                );
            }

            $valueNorm = TextNormalizer::normalize($value);
            if (isset($seen[$valueNorm])) {
                continue;
            }
            $seen[$valueNorm] = true;

            $tagValue = $this->tagValueRepository->create([
                'tag_id'           => $tag->id,
                'value'            => $value,
                'value_normalized' => $valueNorm,
            ]);

            $this->valueAuditLogger->tagValueCreated($actor, $tag, $tagValue);
        }
    }

    /**
     * Import upsert: create the tag when it's new, otherwise merge into the
     * existing one — add any values it doesn't already have and (last-wins)
     * overwrite the description when this row supplies a non-empty one. Bad
     * values are dropped silently here; the importer has already warned about
     * them at preview time. Returns true when anything was actually written, so
     * a re-import of the same file is counted as skipped rather than created.
     *
     * @param  array{name: string, name_normalized?: string, description?: ?string, has_description?: bool, values?: list<string>}  $data
     */
    public function importUpsert(User $actor, int $storeId, array $data): bool
    {
        $this->permissionService->authorizeStore($actor, PermissionEnum::CREATE_TAG, $storeId);

        $name           = trim((string) ($data['name'] ?? ''));
        $nameNorm       = (string) ($data['name_normalized'] ?? TextNormalizer::normalize($name));
        $hasDescription = (bool) ($data['has_description'] ?? false);
        $description    = $hasDescription ? trim((string) ($data['description'] ?? '')) : null;
        $values         = is_array($data['values'] ?? null) ? $data['values'] : [];

        return DB::transaction(function () use ($actor, $storeId, $name, $nameNorm, $hasDescription, $description, $values) {
            $tag = $this->tagRepository->findByNameNormalized($storeId, $nameNorm);
            if ($tag !== null) {
                return $this->mergeIntoExistingTag($actor, $tag, $hasDescription, $description, $values);
            }

            try {
                $tag = $this->tagRepository->create([
                    'store_id'        => $storeId,
                    'name'            => $name,
                    'name_normalized' => $nameNorm,
                    'description'     => ($description === null || $description === '') ? null : $description,
                ]);
            } catch (QueryException $e) {
                if (($e->errorInfo[1] ?? null) !== 1062) {
                    throw $e;
                }
                $tag = $this->tagRepository->findByNameNormalized($storeId, $nameNorm);
                if ($tag === null) {
                    throw $e;
                }
                return $this->mergeIntoExistingTag($actor, $tag, $hasDescription, $description, $values);
            }

            $this->auditLogger->tagCreated($actor, $tag);
            $this->attachNewValues($actor, $tag, $values);

            return true;
        });
    }

    /**
     * @param  list<string>  $values
     */
    private function mergeIntoExistingTag(User $actor, Tag $tag, bool $hasDescription, ?string $description, array $values): bool
    {
        $changed = false;

        if ($hasDescription && $description !== null && $description !== '' && $description !== $tag->description) {
            $tag = $this->tagRepository->update($tag, ['description' => $description]);
            $this->auditLogger->tagUpdated($actor, $tag);
            $changed = true;
        }

        if ($this->attachNewValues($actor, $tag, $values) > 0) {
            $changed = true;
        }

        return $changed;
    }

    /**
     * Add values the tag doesn't already have, skipping blanks, over-long values
     * and duplicates. Returns how many were actually created.
     *
     * @param  list<string>  $values
     */
    private function attachNewValues(User $actor, Tag $tag, array $values): int
    {
        $added = 0;
        $seen = [];

        foreach ($values as $rawValue) {
            $value = trim((string) $rawValue);
            if ($value === '' || mb_strlen($value) > 100) {
                continue;
            }

            $valueNorm = TextNormalizer::normalize($value);
            if ($valueNorm === '' || isset($seen[$valueNorm])) {
                continue;
            }
            $seen[$valueNorm] = true;

            if ($this->tagValueRepository->findByValueNormalized((int) $tag->id, $valueNorm) !== null) {
                continue;
            }

            try {
                $tagValue = $this->tagValueRepository->create([
                    'tag_id'           => (int) $tag->id,
                    'value'            => $value,
                    'value_normalized' => $valueNorm,
                ]);
            } catch (QueryException $e) {
                if (($e->errorInfo[1] ?? null) === 1062) {
                    continue;
                }
                throw $e;
            }

            $this->valueAuditLogger->tagValueCreated($actor, $tag, $tagValue);
            $added++;
        }

        return $added;
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

            $this->auditLogger->tagUpdated($actor, $tag);

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
            $this->auditLogger->tagKeyDeleted($actor, $tagId, $name, $storeId);
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

    public function queueExport(User $user, int $storeId, array $filters = [], ?string $clientId = null): Export
    {
        $this->authorizeView($user, $storeId);

        $type              = ExportTagJob::TYPE;
        $scope             = ExportScope::STORE;
        $scopeName         = Store::find($storeId)?->name;
        $normalizedFilters = $this->normalizeExportFilters($filters);
        $jobClass          = ExportTagJob::class;

        return $this->exportService->queue(
            $user,
            $type,
            $scope,
            $storeId,
            $scopeName,
            $normalizedFilters,
            $jobClass,
            $clientId,
        );
    }

    private function normalizeExportFilters(array $filters): array
    {
        $clean = [];

        if (!empty($filters['search'])) {
            $clean['search'] = (string) $filters['search'];
        }
        if (!empty($filters['ids']) && is_array($filters['ids'])) {
            $ids = array_values(array_unique(array_map('intval', $filters['ids'])));
            sort($ids);
            if (count($ids) > 0) {
                $clean['ids'] = $ids;
            }
        }
        if (!empty($filters['columns']) && is_array($filters['columns'])) {
            $columns = array_values(array_filter(
                TagExport::COLUMN_KEYS,
                fn ($key) => in_array($key, $filters['columns'], true),
            ));
            if (count($columns) > 0 && count($columns) < count(TagExport::COLUMN_KEYS)) {
                $clean['columns'] = $columns;
            }
        }

        return $clean;
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
