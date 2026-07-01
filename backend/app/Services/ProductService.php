<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\ExportScope;
use App\Enums\PermissionEnum;
use App\Exceptions\ProductCategoryException;
use App\Exceptions\ProductException;
use App\Exceptions\UnitException;
use App\Exports\ProductExport;
use App\Jobs\Exports\ExportProductJob;
use App\Models\Export;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\Unit;
use App\Models\User;
use App\Repositories\ProductRepository;
use App\Repositories\Tag\TaggableRepository;
use App\Services\AuditLog\Loggers\ProductAuditLogger;
use App\Services\Tag\TaggableService;
use App\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function __construct(
        private ProductRepository $productRepository,
        private TaggableRepository $taggableRepository,
        private TaggableService $taggableService,
        private PermissionService $permissionService,
        private ProductAuditLogger $auditLogger,
        private ExportService $exportService,
    ) {}

    public function getByTag(User $user, int $storeId, int $tagId, ?int $tagValueId = null, bool $includeInactive = false): Collection
    {
        $this->authorizeView($user, $storeId);
        $ids = $this->taggableRepository->entityIdsByTag($storeId, Product::class, $tagId, $tagValueId);
        return $this->productRepository->byIds($storeId, $ids, $includeInactive);
    }

    public function getAll(User $user, int $storeId, bool $includeInactive = false): Collection
    {
        $this->authorizeView($user, $storeId);
        return $this->productRepository->all($storeId, $includeInactive);
    }

    public function search(User $user, int $storeId, string $query, bool $includeInactive = false, int $limit = 10): Collection
    {
        $this->authorizeView($user, $storeId);
        $needle = TextNormalizer::normalize($query);
        if ($needle === '') {
            return $this->productRepository->all($storeId, $includeInactive)->take($limit);
        }
        return $this->productRepository->searchQuery($storeId, $needle, $includeInactive, $limit)->get();
    }

    public function getById(User $user, int $id): Product
    {
        $product = $this->mustFind($id);
        $this->authorizeView($user, (int) $product->store_id);
        return $product;
    }

    public function create(User $actor, int $storeId, array $data): Product
    {
        $this->permissionService->authorizeStore($actor, PermissionEnum::CREATE_PRODUCT, $storeId);

        return DB::transaction(function () use ($actor, $storeId, $data) {
            $name = (string) $data['name'];
            $unitId = (int) $data['unit_id'];
            $categoryId = (int) $data['product_category_id'];
            $nameNorm = TextNormalizer::normalize($name);

            $this->assertUnitBelongsToStore($unitId, $storeId);

            // lockForUpdate blocks any concurrent create in this category — the
            // sequence increment and product insert happen atomically. Other
            // transactions wait until we commit.
            $category = ProductCategory::query()
                ->where('id', $categoryId)
                ->lockForUpdate()
                ->first();

            if (!$category || (int) $category->store_id !== $storeId) {
                throw new ProductCategoryException(
                    ErrorCode::PRODUCT_CATEGORY_NOT_FOUND,
                    'Category not found in this store.'
                );
            }
            if (!$category->is_active) {
                throw new ProductCategoryException(
                    ErrorCode::PRODUCT_CATEGORY_INACTIVE,
                    "Category '{$category->name}' is inactive."
                );
            }

            $existing = $this->productRepository->findByNameNormalized($storeId, $nameNorm);
            if ($existing !== null) {
                throw new ProductException(
                    ErrorCode::PRODUCT_NAME_TAKEN,
                    "A product with this name already exists: {$existing->name}."
                );
            }

            $nextSequence = (int) $category->last_sequence + 1;
            $code = $category->code . sprintf('%06d', $nextSequence);

            $category->update(['last_sequence' => $nextSequence]);

            try {
                $product = $this->productRepository->create([
                    'store_id'            => $storeId,
                    'product_category_id' => $categoryId,
                    'unit_id'             => $unitId,
                    'code'                => $code,
                    'name'                => $name,
                    'name_normalized'     => $nameNorm,
                    'is_active'           => true,
                ]);
            } catch (QueryException $e) {
                $this->translateQueryException($e, $name);
            }

            if (array_key_exists('tags', $data)) {
                $this->taggableService->syncEntityTags(
                    $actor,
                    PermissionEnum::CREATE_PRODUCT,
                    $storeId,
                    Product::class,
                    (int) $product->id,
                    $data['tags'] ?? []
                );
            }

            $product = $product->fresh(['unit', 'category', 'taggables.tag', 'taggables.tagValue']);
            $this->auditLogger->productCreated($actor, $product);

            return $product;
        });
    }

    public function update(User $actor, int $id, array $data): Product
    {
        return DB::transaction(function () use ($actor, $id, $data) {
            $product = $this->mustFind($id);
            $storeId = (int) $product->store_id;
            $this->permissionService->authorizeStore($actor, PermissionEnum::UPDATE_PRODUCT, $storeId);

            // Code is immutable after create — silently accept matching values, reject changes.
            if (array_key_exists('code', $data)) {
                $submitted = trim((string) $data['code']);
                if ($submitted !== '' && $submitted !== $product->code) {
                    throw new ProductException(
                        ErrorCode::PRODUCT_NAME_TAKEN,
                        'Product code cannot be changed once created.'
                    );
                }
            }

            $patch = [];
            $renamedTo = null;

            if (array_key_exists('name', $data)) {
                $name = (string) $data['name'];
                $nameNorm = TextNormalizer::normalize($name);
                if ($nameNorm !== $product->name_normalized) {
                    $existing = $this->productRepository->findByNameNormalized($storeId, $nameNorm, (int) $product->id);
                    if ($existing !== null) {
                        throw new ProductException(
                            ErrorCode::PRODUCT_NAME_TAKEN,
                            "A product with this name already exists: {$existing->name}."
                        );
                    }
                }
                $patch['name'] = $name;
                $patch['name_normalized'] = $nameNorm;
                $renamedTo = $name;
            }

            if (array_key_exists('unit_id', $data)) {
                $unitId = (int) $data['unit_id'];
                $this->assertUnitBelongsToStore($unitId, $storeId);
                $patch['unit_id'] = $unitId;
            }

            if (array_key_exists('product_category_id', $data)) {
                $categoryId = (int) $data['product_category_id'];
                $this->assertCategoryBelongsToStore($categoryId, $storeId);
                $patch['product_category_id'] = $categoryId;
            }

            if (array_key_exists('is_active', $data)) {
                $patch['is_active'] = (bool) $data['is_active'];
            }

            $hasTags = array_key_exists('tags', $data);

            if (empty($patch) && !$hasTags) {
                return $product;
            }

            $wasActive = (bool) $product->is_active;

            if (!empty($patch)) {
                try {
                    $product = $this->productRepository->update($product, $patch);
                } catch (QueryException $e) {
                    $this->translateQueryException($e, $renamedTo ?? $product->name);
                }
            }

            if ($hasTags) {
                $this->taggableService->syncEntityTags(
                    $actor,
                    PermissionEnum::UPDATE_PRODUCT,
                    $storeId,
                    Product::class,
                    (int) $product->id,
                    $data['tags'] ?? []
                );
            }

            if (array_key_exists('is_active', $patch) && $patch['is_active'] !== $wasActive) {
                if ($patch['is_active']) {
                    $this->auditLogger->productReactivated($actor, $product);
                } else {
                    $this->auditLogger->productDeactivated($actor, $product);
                }
            } else {
                $this->auditLogger->productUpdated($actor, $product);
            }

            return $product->fresh(['unit', 'category', 'taggables.tag', 'taggables.tagValue']);
        });
    }

    public function delete(User $actor, int $id): void
    {
        $product = $this->mustFind($id);
        $storeId = (int) $product->store_id;
        $this->permissionService->authorizeStore($actor, PermissionEnum::DELETE_PRODUCT, $storeId);

        $productId = (int) $product->id;
        $code = (string) $product->code;
        $name = (string) $product->name;

        DB::transaction(function () use ($actor, $product, $productId, $code, $name, $storeId) {
            $this->productRepository->delete($product);
            $this->auditLogger->productDeleted($actor, $productId, $code, $name, $storeId);
        });
    }

    // Additive bulk tag attach; only store-owned ids are touched, audit per changed row in the same transaction.
    public function bulkAttachTags(User $actor, int $storeId, array $productIds, array $pairs): Collection
    {
        $this->permissionService->authorizeStore($actor, PermissionEnum::UPDATE_PRODUCT, $storeId);

        $ids = array_values(array_unique(array_map('intval', $productIds)));
        $products = $this->productRepository->byIds($storeId, $ids, true);
        if ($products->isEmpty()) {
            return $products;
        }

        $validIds = $products->map(fn (Product $product) => (int) $product->id)->all();

        return DB::transaction(function () use ($actor, $storeId, $validIds, $pairs) {
            $attached = $this->taggableService->attachTagsToEntities(
                $actor,
                PermissionEnum::UPDATE_PRODUCT,
                $storeId,
                Product::class,
                $validIds,
                $pairs
            );

            $refreshed = $this->productRepository->byIds($storeId, $validIds, true);
            foreach ($refreshed as $product) {
                $newPairs = $attached[(int) $product->id] ?? [];
                if (!empty($newPairs)) {
                    $this->auditLogger->productTagsAttached($actor, $product, $newPairs);
                }
            }

            return $refreshed;
        });
    }

    // Inverse of bulkAttachTags; audits the removed labels from the pre-detach snapshot (they're gone after).
    public function bulkDetachTags(User $actor, int $storeId, array $productIds, array $pairs): Collection
    {
        $this->permissionService->authorizeStore($actor, PermissionEnum::UPDATE_PRODUCT, $storeId);

        $ids = array_values(array_unique(array_map('intval', $productIds)));
        $products = $this->productRepository->byIds($storeId, $ids, true);
        if ($products->isEmpty()) {
            return $products;
        }

        $validIds = $products->map(fn (Product $product) => (int) $product->id)->all();

        return DB::transaction(function () use ($actor, $storeId, $validIds, $pairs, $products) {
            $detached = $this->taggableService->detachTagsFromEntities(
                $actor,
                PermissionEnum::UPDATE_PRODUCT,
                $storeId,
                Product::class,
                $validIds,
                $pairs
            );

            foreach ($products as $product) {
                $removedPairs = $detached[(int) $product->id] ?? [];
                if (!empty($removedPairs)) {
                    $this->auditLogger->productTagsDetached($actor, $product, $removedPairs);
                }
            }

            return $this->productRepository->byIds($storeId, $validIds, true);
        });
    }

    public function queueExport(User $user, int $storeId, array $filters = [], ?string $clientId = null): Export
    {
        $this->authorizeView($user, $storeId);

        $type              = ExportProductJob::TYPE;
        $scope             = ExportScope::STORE;
        $scopeName         = Store::find($storeId)?->name;
        $normalizedFilters = $this->normalizeExportFilters($filters);
        $jobClass          = ExportProductJob::class;

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
        if (in_array($filters['status'] ?? null, ['active', 'inactive'], true)) {
            $clean['status'] = (string) $filters['status'];
        }
        if (!empty($filters['unit_id'])) {
            $clean['unit_id'] = (int) $filters['unit_id'];
        }
        if (!empty($filters['category_id'])) {
            $clean['category_id'] = (int) $filters['category_id'];
        }
        if (!empty($filters['tag_ids']) && is_array($filters['tag_ids'])) {
            $tagIds = array_values(array_unique(array_filter(array_map('intval', $filters['tag_ids']))));
            if (count($tagIds) > 0) {
                $clean['tag_ids'] = $tagIds;
            }
        }
        if (!empty($filters['tag_value_ids']) && is_array($filters['tag_value_ids'])) {
            $tagValueIds = array_values(array_unique(array_filter(array_map('intval', $filters['tag_value_ids']))));
            if (count($tagValueIds) > 0) {
                $clean['tag_value_ids'] = $tagValueIds;
            }
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
                ProductExport::COLUMN_KEYS,
                fn ($key) => in_array($key, $filters['columns'], true),
            ));
            if (count($columns) > 0 && count($columns) < count(ProductExport::COLUMN_KEYS)) {
                $clean['columns'] = $columns;
            }
        }

        return $clean;
    }

    private function authorizeView(User $user, int $storeId): void
    {
        $this->permissionService->authorizeStore($user, PermissionEnum::UPDATE_PRODUCT, $storeId);
    }

    private function mustFind(int $id): Product
    {
        $product = $this->productRepository->findById($id);
        if (!$product) {
            throw new ProductException(ErrorCode::PRODUCT_NOT_FOUND, 'Product not found.');
        }
        return $product;
    }

    /**
     * sharedLock blocks concurrent unit deletes/deactivations until our
     * transaction commits — closes the TOCTOU window between check and write.
     */
    private function assertUnitBelongsToStore(int $unitId, int $storeId): void
    {
        $unit = Unit::query()->where('id', $unitId)->sharedLock()->first();
        if (!$unit || (int) $unit->store_id !== $storeId) {
            throw new UnitException(ErrorCode::UNIT_NOT_FOUND, 'Unit not found in this store.');
        }
        if (!$unit->is_active) {
            throw new UnitException(ErrorCode::UNIT_INACTIVE, "Unit '{$unit->name}' is inactive.");
        }
    }

    private function assertCategoryBelongsToStore(int $categoryId, int $storeId): void
    {
        $category = ProductCategory::query()->where('id', $categoryId)->sharedLock()->first();
        if (!$category || (int) $category->store_id !== $storeId) {
            throw new ProductCategoryException(
                ErrorCode::PRODUCT_CATEGORY_NOT_FOUND,
                'Category not found in this store.'
            );
        }
        if (!$category->is_active) {
            throw new ProductCategoryException(
                ErrorCode::PRODUCT_CATEGORY_INACTIVE,
                "Category '{$category->name}' is inactive."
            );
        }
    }

    /**
     * 1062 = duplicate-key (concurrent name insert won the race, or a code
     * collision we shouldn't normally hit since the category lock owns the
     * counter). 1452 = FK violation (referenced unit/category deleted between
     * our shared lock release and the write — defensive fallback).
     */
    private function translateQueryException(QueryException $e, string $name): never
    {
        $code = $e->errorInfo[1] ?? null;
        if ($code === 1062) {
            $msg = (string) ($e->errorInfo[2] ?? '');
            if (str_contains($msg, 'name_normalized')) {
                throw new ProductException(ErrorCode::PRODUCT_NAME_TAKEN, "A product with this name already exists: {$name}.");
            }
            throw new ProductException(ErrorCode::PRODUCT_NAME_TAKEN, 'A product with this code already exists.');
        }
        if ($code === 1452) {
            throw new UnitException(ErrorCode::UNIT_NOT_FOUND, 'Selected unit or category no longer exists.');
        }
        throw $e;
    }
}
