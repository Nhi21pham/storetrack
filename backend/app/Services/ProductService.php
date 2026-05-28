<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\PermissionEnum;
use App\Exceptions\ProductException;
use App\Exceptions\UnitException;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Repositories\ProductRepository;
use App\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function __construct(
        private ProductRepository $productRepository,
        private PermissionService $permissionService,
        private AuditLogService $auditLogService,
    ) {}

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
            $name     = (string) $data['name'];
            $unitId   = (int) $data['unit_id'];
            $nameNorm = TextNormalizer::normalize($name);

            // sharedLock blocks any concurrent transaction trying to delete/update this
            // unit row until our transaction commits — closes the TOCTOU window
            // between checking the unit and inserting the product.
            $this->assertUnitBelongsToStore($unitId, $storeId);

            $existing = $this->productRepository->findByNameNormalized($storeId, $nameNorm);
            if ($existing !== null) {
                throw new ProductException(ErrorCode::PRODUCT_NAME_TAKEN, "A product with this name already exists: {$existing->name}.");
            }

            try {
                $product = $this->productRepository->create([
                    'store_id'        => $storeId,
                    'unit_id'         => $unitId,
                    'name'            => $name,
                    'name_normalized' => $nameNorm,
                    'is_active'       => true,
                ]);
            } catch (QueryException $e) {
                $this->translateQueryException($e, $name);
            }

            $product = $product->fresh(['unit']);
            $this->auditLogService->productCreated($actor, $product);

            return $product;
        });
    }

    public function update(User $actor, int $id, array $data): Product
    {
        return DB::transaction(function () use ($actor, $id, $data) {
            $product = $this->mustFind($id);
            $storeId = (int) $product->store_id;
            $this->permissionService->authorizeStore($actor, PermissionEnum::UPDATE_PRODUCT, $storeId);

            $patch = [];
            $renamedTo = null;

            if (array_key_exists('name', $data)) {
                $name = (string) $data['name'];
                $nameNorm = TextNormalizer::normalize($name);
                if ($nameNorm !== $product->name_normalized) {
                    $existing = $this->productRepository->findByNameNormalized($storeId, $nameNorm, (int) $product->id);
                    if ($existing !== null) {
                        throw new ProductException(ErrorCode::PRODUCT_NAME_TAKEN, "A product with this name already exists: {$existing->name}.");
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

            if (array_key_exists('is_active', $data)) {
                $patch['is_active'] = (bool) $data['is_active'];
            }

            if (empty($patch)) {
                return $product;
            }

            $wasActive = (bool) $product->is_active;

            try {
                $product = $this->productRepository->update($product, $patch);
            } catch (QueryException $e) {
                $this->translateQueryException($e, $renamedTo ?? $product->name);
            }

            if (array_key_exists('is_active', $patch) && $patch['is_active'] !== $wasActive) {
                if ($patch['is_active']) {
                    $this->auditLogService->productReactivated($actor, $product);
                } else {
                    $this->auditLogService->productDeactivated($actor, $product);
                }
            } else {
                $this->auditLogService->productUpdated($actor, $product);
            }

            return $product;
        });
    }

    public function delete(User $actor, int $id): void
    {
        $product = $this->mustFind($id);
        $storeId = (int) $product->store_id;
        $this->permissionService->authorizeStore($actor, PermissionEnum::DELETE_PRODUCT, $storeId);

        $productId = (int) $product->id;
        $name = (string) $product->name;

        DB::transaction(function () use ($actor, $product, $productId, $name, $storeId) {
            $this->productRepository->delete($product);
            $this->auditLogService->productDeleted($actor, $productId, $name, $storeId);
        });
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
     * Verify the unit exists, belongs to this store, and is active. Uses a
     * shared lock so the row can't be deleted/deactivated between this check
     * and the product insert that follows in the same transaction.
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

    /**
     * Map the MySQL/Eloquent QueryException codes we care about to clean
     * domain exceptions. 1062 = duplicate-key (concurrent name insert won the
     * race); 1452 = FK violation (unit was deleted between our shared lock
     * release and the write — defensive fallback).
     */
    private function translateQueryException(QueryException $e, string $name): never
    {
        $code = $e->errorInfo[1] ?? null;
        if ($code === 1062) {
            throw new ProductException(ErrorCode::PRODUCT_NAME_TAKEN, "A product with this name already exists: {$name}.");
        }
        if ($code === 1452) {
            throw new UnitException(ErrorCode::UNIT_NOT_FOUND, 'Selected unit no longer exists.');
        }
        throw $e;
    }
}
