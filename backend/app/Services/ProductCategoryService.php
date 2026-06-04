<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\PermissionEnum;
use App\Exceptions\ProductCategoryException;
use App\Models\ProductCategory;
use App\Models\User;
use App\Repositories\ProductCategoryRepository;
use App\Services\AuditLog\Loggers\ProductCategoryAuditLogger;
use App\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ProductCategoryService
{
    private const CODE_REGEX = '/^[A-Z]{2,10}$/';

    public function __construct(
        private ProductCategoryRepository $productCategoryRepository,
        private PermissionService $permissionService,
        private ProductCategoryAuditLogger $auditLogger,
    ) {}

    public function getAll(User $user, int $storeId, bool $includeInactive = false): Collection
    {
        $this->authorizeView($user, $storeId);
        return $this->productCategoryRepository->all($storeId, $includeInactive);
    }

    public function search(User $user, int $storeId, string $query, bool $includeInactive = false, int $limit = 10): Collection
    {
        $this->authorizeView($user, $storeId);
        $needle = TextNormalizer::normalize($query);
        if ($needle === '') {
            return $this->productCategoryRepository->all($storeId, $includeInactive)->take($limit);
        }
        return $this->productCategoryRepository->searchQuery($storeId, $needle, $includeInactive, $limit)->get();
    }

    public function getById(User $user, int $id): ProductCategory
    {
        $category = $this->mustFind($id);
        $this->authorizeView($user, (int) $category->store_id);
        return $category;
    }

    public function create(User $actor, int $storeId, array $data): ProductCategory
    {
        $this->permissionService->authorizeStore($actor, PermissionEnum::CREATE_PRODUCT_CATEGORY, $storeId);

        return DB::transaction(function () use ($actor, $storeId, $data) {
            $code = strtoupper(trim((string) $data['code']));
            $name = (string) $data['name'];
            $description = isset($data['description']) ? (string) $data['description'] : null;
            $nameNorm = TextNormalizer::normalize($name);

            $this->assertValidCode($code);

            $existingByCode = $this->productCategoryRepository->findByCode($storeId, $code);
            if ($existingByCode !== null) {
                throw new ProductCategoryException(
                    ErrorCode::PRODUCT_CATEGORY_CODE_TAKEN,
                    "A category with code '{$code}' already exists."
                );
            }
            $existingByName = $this->productCategoryRepository->findByNameNormalized($storeId, $nameNorm);
            if ($existingByName !== null) {
                throw new ProductCategoryException(
                    ErrorCode::PRODUCT_CATEGORY_NAME_TAKEN,
                    "A category with this name already exists: {$existingByName->name}."
                );
            }

            try {
                $category = $this->productCategoryRepository->create([
                    'store_id'        => $storeId,
                    'code'            => $code,
                    'name'            => $name,
                    'name_normalized' => $nameNorm,
                    'description'     => $description,
                    'is_active'       => true,
                    'is_system'       => false,
                    'last_sequence'   => 0,
                ]);
            } catch (QueryException $e) {
                $this->translateUniqueViolation($e, $code, $name);
            }

            $this->auditLogger->productCategoryCreated($actor, $category);

            return $category;
        });
    }

    public function update(User $actor, int $id, array $data): ProductCategory
    {
        return DB::transaction(function () use ($actor, $id, $data) {
            $category = $this->mustFind($id);
            $storeId = (int) $category->store_id;
            $this->permissionService->authorizeStore($actor, PermissionEnum::UPDATE_PRODUCT_CATEGORY, $storeId);

            if ($category->is_system) {
                throw new ProductCategoryException(
                    ErrorCode::PRODUCT_CATEGORY_SYSTEM_LOCKED,
                    "System categories cannot be edited."
                );
            }

            // Code is immutable after create — silently accept matching values, reject changes.
            if (array_key_exists('code', $data)) {
                $submitted = strtoupper(trim((string) $data['code']));
                if ($submitted !== '' && $submitted !== $category->code) {
                    throw new ProductCategoryException(
                        ErrorCode::PRODUCT_CATEGORY_CODE_IMMUTABLE,
                        "Category code cannot be changed once created."
                    );
                }
            }

            $patch = [];
            $renamedTo = null;

            if (array_key_exists('name', $data)) {
                $name = (string) $data['name'];
                $nameNorm = TextNormalizer::normalize($name);
                if ($nameNorm !== $category->name_normalized) {
                    $existing = $this->productCategoryRepository->findByNameNormalized($storeId, $nameNorm, (int) $category->id);
                    if ($existing !== null) {
                        throw new ProductCategoryException(
                            ErrorCode::PRODUCT_CATEGORY_NAME_TAKEN,
                            "A category with this name already exists: {$existing->name}."
                        );
                    }
                }
                $patch['name'] = $name;
                $patch['name_normalized'] = $nameNorm;
                $renamedTo = $name;
            }

            if (array_key_exists('description', $data)) {
                $description = $data['description'];
                $patch['description'] = ($description === null || $description === '') ? null : (string) $description;
            }

            if (array_key_exists('is_active', $data)) {
                $patch['is_active'] = (bool) $data['is_active'];
            }

            if (empty($patch)) {
                return $category;
            }

            $wasActive = (bool) $category->is_active;

            try {
                $category = $this->productCategoryRepository->update($category, $patch);
            } catch (QueryException $e) {
                $this->translateUniqueViolation($e, $category->code, $renamedTo ?? $category->name);
            }

            if (array_key_exists('is_active', $patch) && $patch['is_active'] !== $wasActive) {
                if ($patch['is_active']) {
                    $this->auditLogger->productCategoryReactivated($actor, $category);
                } else {
                    $this->auditLogger->productCategoryDeactivated($actor, $category);
                }
            } else {
                $this->auditLogger->productCategoryUpdated($actor, $category);
            }

            return $category;
        });
    }

    public function delete(User $actor, int $id): void
    {
        $category = $this->mustFind($id);
        $storeId = (int) $category->store_id;
        $this->permissionService->authorizeStore($actor, PermissionEnum::DELETE_PRODUCT_CATEGORY, $storeId);

        if ($category->is_system) {
            throw new ProductCategoryException(
                ErrorCode::PRODUCT_CATEGORY_SYSTEM_LOCKED,
                "System categories cannot be deleted."
            );
        }

        $categoryId = (int) $category->id;
        $code = (string) $category->code;
        $name = (string) $category->name;

        DB::transaction(function () use ($actor, $category, $categoryId, $code, $name, $storeId) {
            $this->productCategoryRepository->delete($category);
            $this->auditLogger->productCategoryDeleted($actor, $categoryId, $code, $name, $storeId);
        });
    }

    private function authorizeView(User $user, int $storeId): void
    {
        $this->permissionService->authorizeStore($user, PermissionEnum::UPDATE_PRODUCT_CATEGORY, $storeId);
    }

    private function mustFind(int $id): ProductCategory
    {
        $category = $this->productCategoryRepository->findById($id);
        if (!$category) {
            throw new ProductCategoryException(ErrorCode::PRODUCT_CATEGORY_NOT_FOUND, 'Product category not found.');
        }
        return $category;
    }

    private function assertValidCode(string $code): void
    {
        if (!preg_match(self::CODE_REGEX, $code)) {
            throw new ProductCategoryException(
                ErrorCode::PRODUCT_CATEGORY_CODE_TAKEN,
                'Code must be 2-10 uppercase letters (A-Z).'
            );
        }
    }

    private function translateUniqueViolation(QueryException $e, string $code, string $name): never
    {
        if (($e->errorInfo[1] ?? null) === 1062) {
            $msg = (string) ($e->errorInfo[2] ?? '');
            if (str_contains($msg, 'name_normalized')) {
                throw new ProductCategoryException(
                    ErrorCode::PRODUCT_CATEGORY_NAME_TAKEN,
                    "A category with this name already exists: {$name}."
                );
            }
            throw new ProductCategoryException(
                ErrorCode::PRODUCT_CATEGORY_CODE_TAKEN,
                "A category with code '{$code}' already exists."
            );
        }
        throw $e;
    }
}
