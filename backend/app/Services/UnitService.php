<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\PermissionEnum;
use App\Exceptions\UnitException;
use App\Exports\UnitExport;
use App\Jobs\Exports\ExportUnitJob;
use App\Models\Export;
use App\Models\Store;
use App\Models\Unit;
use App\Models\User;
use App\Repositories\ExportRepository;
use App\Repositories\UnitRepository;
use App\Services\AuditLog\Loggers\UnitAuditLogger;
use App\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class UnitService
{
    public function __construct(
        private UnitRepository $unitRepository,
        private PermissionService $permissionService,
        private UnitAuditLogger $auditLogger,
        private ExportService $exportService,
        private ExportRepository $exportRepository,
    ) {}

    public function getAll(User $user, int $storeId, bool $includeInactive = false): Collection
    {
        $this->authorizeView($user, $storeId);
        return $this->unitRepository->all($storeId, $includeInactive);
    }

    public function search(User $user, int $storeId, string $query, bool $includeInactive = false, int $limit = 10): Collection
    {
        $this->authorizeView($user, $storeId);
        $needle = TextNormalizer::normalize($query);
        if ($needle === '') {
            return $this->unitRepository->all($storeId, $includeInactive)->take($limit);
        }
        return $this->unitRepository->searchQuery($storeId, $needle, $includeInactive, $limit)->get();
    }

    public function getById(User $user, int $id): Unit
    {
        $unit = $this->mustFind($id);
        $this->authorizeView($user, (int) $unit->store_id);
        return $unit;
    }

    public function create(User $actor, int $storeId, array $data): Unit
    {
        $this->permissionService->authorizeStore($actor, PermissionEnum::CREATE_UNIT, $storeId);

        return DB::transaction(function () use ($actor, $storeId, $data) {
            $name = (string) $data['name'];
            $nameNorm = TextNormalizer::normalize($name);

            $existing = $this->unitRepository->findByNameNormalized($storeId, $nameNorm);
            if ($existing !== null) {
                throw new UnitException(ErrorCode::UNIT_NAME_TAKEN, "A unit with this name already exists: {$existing->name}.");
            }

            try {
                $unit = $this->unitRepository->create([
                    'store_id'        => $storeId,
                    'name'            => $name,
                    'name_normalized' => $nameNorm,
                    'is_active'       => true,
                ]);
            } catch (QueryException $e) {
                // (store_id, name_normalized) unique index — concurrent insert won the race
                if (($e->errorInfo[1] ?? null) === 1062) {
                    throw new UnitException(ErrorCode::UNIT_NAME_TAKEN, "A unit with this name already exists: {$name}.");
                }
                throw $e;
            }

            $this->auditLogger->unitCreated($actor, $unit);

            return $unit;
        });
    }

    public function update(User $actor, int $id, array $data): Unit
    {
        return DB::transaction(function () use ($actor, $id, $data) {
            $unit = $this->mustFind($id);
            $storeId = (int) $unit->store_id;
            $this->permissionService->authorizeStore($actor, PermissionEnum::UPDATE_UNIT, $storeId);

            $patch = [];
            $renamedTo = null;

            if (array_key_exists('name', $data)) {
                $name = (string) $data['name'];
                $nameNorm = TextNormalizer::normalize($name);
                if ($nameNorm !== $unit->name_normalized) {
                    $existing = $this->unitRepository->findByNameNormalized($storeId, $nameNorm, (int) $unit->id);
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
                    $this->auditLogger->unitReactivated($actor, $unit);
                } else {
                    $this->auditLogger->unitDeactivated($actor, $unit);
                }
            } else {
                $this->auditLogger->unitUpdated($actor, $unit);
            }

            return $unit;
        });
    }

    public function delete(User $actor, int $id): void
    {
        $unit = $this->mustFind($id);
        $storeId = (int) $unit->store_id;
        $this->permissionService->authorizeStore($actor, PermissionEnum::DELETE_UNIT, $storeId);

        if ($this->unitRepository->hasProducts((int) $unit->id)) {
            throw new UnitException(
                ErrorCode::UNIT_IN_USE,
                "Unit {$unit->name} is referenced by existing products. Deactivate it instead of deleting."
            );
        }

        $unitId = (int) $unit->id;
        $name = (string) $unit->name;

        DB::transaction(function () use ($actor, $unit, $unitId, $name, $storeId) {
            $this->unitRepository->delete($unit);
            $this->auditLogger->unitDeleted($actor, $unitId, $name, $storeId);
        });
    }

    public function queueExport(User $user, int $storeId, array $filters = [], ?string $clientId = null): Export
    {
        $this->authorizeView($user, $storeId);

        $normalizedFilters = $this->normalizeExportFilters($filters);
        $filterSignature   = $this->filterSignature($normalizedFilters);
        $type              = ExportUnitJob::TYPE;

        $inProgress = $this->exportRepository->findInProgressDuplicate($user->id, $type, $storeId, $filterSignature, $clientId);
        if ($inProgress) {
            return $inProgress;
        }

        $reusable = $this->exportRepository->findCompletedDuplicateWithFile($user->id, $type, $storeId, $filterSignature, $clientId);
        if ($reusable) {
            return $reusable;
        }

        $existing = $this->exportRepository->findExistingFilesForScope($user->id, $type, $storeId, $clientId);
        foreach ($existing as $old) {
            $this->exportService->deleteFile($old);
        }

        $store = Store::find($storeId);

        $export = $this->exportService->createPending(
            $user,
            $type,
            [
                'scope'            => 'store',
                'scope_id'         => $storeId,
                'scope_name'       => $store?->name,
                'filters'          => $normalizedFilters,
                'filter_signature' => $filterSignature,
                'client_id'        => $clientId,
            ]
        );

        ExportUnitJob::dispatch($export->id);

        return $export;
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
        if (!empty($filters['ids']) && is_array($filters['ids'])) {
            $ids = array_values(array_unique(array_map('intval', $filters['ids'])));
            sort($ids);
            if (count($ids) > 0) {
                $clean['ids'] = $ids;
            }
        }
        if (!empty($filters['columns']) && is_array($filters['columns'])) {
            $columns = array_values(array_filter(
                UnitExport::COLUMN_KEYS,
                fn ($key) => in_array($key, $filters['columns'], true),
            ));
            if (count($columns) > 0 && count($columns) < count(UnitExport::COLUMN_KEYS)) {
                $clean['columns'] = $columns;
            }
        }

        return $clean;
    }

    private function filterSignature(array $filters): string
    {
        ksort($filters);
        return sha1((string) json_encode($filters));
    }

    private function authorizeView(User $user, int $storeId): void
    {
        $this->permissionService->authorizeStore($user, PermissionEnum::UPDATE_UNIT, $storeId);
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
