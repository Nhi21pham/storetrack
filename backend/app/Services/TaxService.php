<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\PermissionEnum;
use App\Exceptions\TaxException;
use App\Models\Tax;
use App\Models\User;
use App\Repositories\TaxRepository;
use App\Services\AuditLog\Loggers\TaxAuditLogger;
use App\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class TaxService
{
    public function __construct(
        private TaxRepository $taxRepository,
        private PermissionService $permissionService,
        private TaxAuditLogger $auditLogger,
    ) {}

    public function getAll(User $user, int $storeId, bool $includeInactive = false): Collection
    {
        $this->authorizeView($user, $storeId);
        return $this->taxRepository->all($storeId, $includeInactive);
    }

    public function search(User $user, int $storeId, string $query, bool $includeInactive = false, int $limit = 10): Collection
    {
        $this->authorizeView($user, $storeId);
        $needle = TextNormalizer::normalize($query);
        if ($needle === '') {
            return $this->taxRepository->all($storeId, $includeInactive)->take($limit);
        }
        return $this->taxRepository->searchQuery($storeId, $needle, $includeInactive, $limit)->get();
    }

    public function getById(User $user, int $id): Tax
    {
        $tax = $this->mustFind($id);
        $this->authorizeView($user, (int) $tax->store_id);
        return $tax;
    }

    public function create(User $actor, int $storeId, array $data): Tax
    {
        $this->permissionService->authorizeStore($actor, PermissionEnum::CREATE_TAX, $storeId);

        return DB::transaction(function () use ($actor, $storeId, $data) {
            $name = (string) $data['name'];
            $description = isset($data['description']) ? (string) $data['description'] : null;
            $nameNorm = TextNormalizer::normalize($name);

            $existing = $this->taxRepository->findByNameNormalized($storeId, $nameNorm);
            if ($existing !== null) {
                throw new TaxException(
                    ErrorCode::TAX_NAME_TAKEN,
                    "A tax with this name already exists: {$existing->name}."
                );
            }

            try {
                $tax = $this->taxRepository->create([
                    'store_id'        => $storeId,
                    'name'            => $name,
                    'name_normalized' => $nameNorm,
                    'description'     => $description,
                    'is_active'       => true,
                    'is_system'       => false,
                ]);
            } catch (QueryException $e) {
                $this->translateUniqueViolation($e, $name);
            }

            $this->auditLogger->taxCreated($actor, $tax);

            return $tax;
        });
    }

    public function update(User $actor, int $id, array $data): Tax
    {
        return DB::transaction(function () use ($actor, $id, $data) {
            $tax = $this->mustFind($id);
            $storeId = (int) $tax->store_id;
            $this->permissionService->authorizeStore($actor, PermissionEnum::UPDATE_TAX, $storeId);

            if ($tax->is_system) {
                throw new TaxException(
                    ErrorCode::TAX_SYSTEM_LOCKED,
                    'System taxes cannot be edited.'
                );
            }

            $patch = [];
            $renamedTo = null;

            if (array_key_exists('name', $data)) {
                $name = (string) $data['name'];
                $nameNorm = TextNormalizer::normalize($name);
                if ($nameNorm !== $tax->name_normalized) {
                    $existing = $this->taxRepository->findByNameNormalized($storeId, $nameNorm, (int) $tax->id);
                    if ($existing !== null) {
                        throw new TaxException(
                            ErrorCode::TAX_NAME_TAKEN,
                            "A tax with this name already exists: {$existing->name}."
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
                return $tax;
            }

            $wasActive = (bool) $tax->is_active;

            try {
                $tax = $this->taxRepository->update($tax, $patch);
            } catch (QueryException $e) {
                $this->translateUniqueViolation($e, $renamedTo ?? $tax->name);
            }

            if (array_key_exists('is_active', $patch) && $patch['is_active'] !== $wasActive) {
                if ($patch['is_active']) {
                    $this->auditLogger->taxReactivated($actor, $tax);
                } else {
                    $this->auditLogger->taxDeactivated($actor, $tax);
                }
            } else {
                $this->auditLogger->taxUpdated($actor, $tax);
            }

            return $tax;
        });
    }

    public function delete(User $actor, int $id): void
    {
        $tax = $this->mustFind($id);
        $storeId = (int) $tax->store_id;
        $this->permissionService->authorizeStore($actor, PermissionEnum::DELETE_TAX, $storeId);

        if ($tax->is_system) {
            throw new TaxException(
                ErrorCode::TAX_SYSTEM_LOCKED,
                'System taxes cannot be deleted.'
            );
        }

        $taxId = (int) $tax->id;
        $name = (string) $tax->name;

        DB::transaction(function () use ($actor, $tax, $taxId, $name, $storeId) {
            $this->taxRepository->delete($tax);
            $this->auditLogger->taxDeleted($actor, $taxId, $name, $storeId);
        });
    }

    private function authorizeView(User $user, int $storeId): void
    {
        $this->permissionService->authorizeStore($user, PermissionEnum::UPDATE_TAX, $storeId);
    }

    private function mustFind(int $id): Tax
    {
        $tax = $this->taxRepository->findById($id);
        if (!$tax) {
            throw new TaxException(ErrorCode::TAX_NOT_FOUND, 'Tax not found.');
        }
        return $tax;
    }

    private function translateUniqueViolation(QueryException $e, string $name): never
    {
        if (($e->errorInfo[1] ?? null) === 1062) {
            throw new TaxException(
                ErrorCode::TAX_NAME_TAKEN,
                "A tax with this name already exists: {$name}."
            );
        }
        throw $e;
    }
}
