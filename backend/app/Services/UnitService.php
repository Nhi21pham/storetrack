<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\PermissionEnum;
use App\Exceptions\UnitException;
use App\Models\Unit;
use App\Models\User;
use App\Repositories\UnitRepository;
use App\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class UnitService
{
    public function __construct(
        private UnitRepository $unitRepository,
        private PermissionService $permissionService,
        private AuditLogService $auditLogService,
    ) {}

    public function getAll(User $user, int $businessId, bool $includeInactive = false): Collection
    {
        $this->authorizeView($user, $businessId);
        return $this->unitRepository->all($businessId, $includeInactive);
    }

    public function search(User $user, int $businessId, string $query, bool $includeInactive = false, int $limit = 10): Collection
    {
        $this->authorizeView($user, $businessId);
        $needle = TextNormalizer::normalize($query);
        if ($needle === '') {
            return $this->unitRepository->all($businessId, $includeInactive)->take($limit);
        }
        return $this->unitRepository->searchQuery($businessId, $needle, $includeInactive, $limit)->get();
    }

    public function getById(User $user, int $id): Unit
    {
        $unit = $this->mustFind($id);
        $this->authorizeView($user, (int) $unit->business_id);
        return $unit;
    }

    public function create(User $actor, int $businessId, array $data): Unit
    {
        $this->permissionService->authorizeAnyStoreInBusiness($actor, PermissionEnum::CREATE_UNIT, $businessId);

        return DB::transaction(function () use ($actor, $businessId, $data) {
            $name = (string) $data['name'];
            $nameNorm = TextNormalizer::normalize($name);

            $existing = $this->unitRepository->findByNameNormalized($businessId, $nameNorm);
            if ($existing !== null) {
                throw new UnitException(ErrorCode::UNIT_NAME_TAKEN, "A unit with this name already exists: {$existing->name}.");
            }

            try {
                $unit = $this->unitRepository->create([
                    'business_id'     => $businessId,
                    'name'            => $name,
                    'name_normalized' => $nameNorm,
                    'is_active'       => true,
                ]);
            } catch (QueryException $e) {
                // (business_id, name_normalized) unique index — concurrent insert won the race
                if (($e->errorInfo[1] ?? null) === 1062) {
                    throw new UnitException(ErrorCode::UNIT_NAME_TAKEN, "A unit with this name already exists: {$name}.");
                }
                throw $e;
            }

            $this->auditLogService->unitCreated($actor, $unit);

            return $unit;
        });
    }

    public function update(User $actor, int $id, array $data): Unit
    {
        return DB::transaction(function () use ($actor, $id, $data) {
            $unit = $this->mustFind($id);
            $businessId = (int) $unit->business_id;
            $this->permissionService->authorizeAnyStoreInBusiness($actor, PermissionEnum::UPDATE_UNIT, $businessId);

            $patch = [];
            $renamedTo = null;

            if (array_key_exists('name', $data)) {
                $name = (string) $data['name'];
                $nameNorm = TextNormalizer::normalize($name);
                if ($nameNorm !== $unit->name_normalized) {
                    $existing = $this->unitRepository->findByNameNormalized($businessId, $nameNorm, (int) $unit->id);
                    if ($existing !== null) {
                        throw new UnitException(ErrorCode::UNIT_NAME_TAKEN, "A unit with this name already exists: {$existing->name}.");
                    }
                }
                $patch['name'] = $name;
                $patch['name_normalized'] = $nameNorm;
                $renamedTo = $name;
            }

            if (array_key_exists('is_active', $data)) {
                $patch['is_active'] = (bool) $data['is_active'];
            }

            if (empty($patch)) {
                return $unit;
            }

            $wasActive = (bool) $unit->is_active;

            try {
                $unit = $this->unitRepository->update($unit, $patch);
            } catch (QueryException $e) {
                if (($e->errorInfo[1] ?? null) === 1062) {
                    throw new UnitException(ErrorCode::UNIT_NAME_TAKEN, "A unit with this name already exists: {$renamedTo}.");
                }
                throw $e;
            }

            if (array_key_exists('is_active', $patch) && $patch['is_active'] !== $wasActive) {
                if ($patch['is_active']) {
                    $this->auditLogService->unitReactivated($actor, $unit);
                } else {
                    $this->auditLogService->unitDeactivated($actor, $unit);
                }
            } else {
                $this->auditLogService->unitUpdated($actor, $unit);
            }

            return $unit;
        });
    }

    public function delete(User $actor, int $id): void
    {
        $unit = $this->mustFind($id);
        $businessId = (int) $unit->business_id;
        $this->permissionService->authorizeAnyStoreInBusiness($actor, PermissionEnum::DELETE_UNIT, $businessId);

        $unitId = (int) $unit->id;
        $name = (string) $unit->name;

        DB::transaction(function () use ($actor, $unit, $unitId, $name, $businessId) {
            $this->unitRepository->delete($unit);
            $this->auditLogService->unitDeleted($actor, $unitId, $name, $businessId);
        });
    }

    private function authorizeView(User $user, int $businessId): void
    {
        $this->permissionService->authorizeAnyStoreInBusiness($user, PermissionEnum::UPDATE_UNIT, $businessId);
    }

    private function mustFind(int $id): Unit
    {
        $unit = $this->unitRepository->findById($id);
        if (!$unit) {
            throw new UnitException(ErrorCode::UNIT_NOT_FOUND, 'Unit not found.');
        }
        return $unit;
    }
}
