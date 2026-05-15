<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\PartyTypeEnum;
use App\Exceptions\SupplierException;
use App\Exceptions\AuthorizationException;
use App\Jobs\Exports\ExportSupplierJob;
use App\Models\Business;
use App\Models\Export;
use App\Models\Supplier;
use App\Models\User;
use App\Repositories\ExportRepository;
use App\Repositories\PartyRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\PermissionRepository;
use Illuminate\Support\Facades\DB;

class SupplierService
{
    public function __construct(
        private SupplierRepository $supplierRepository,
        private PartyRepository $partyRepository,
        private AuditLogService $auditLogService,
        private PermissionRepository $permissionRepository,
        private ExportService $exportService,
        private ExportRepository $exportRepository,
    ) {}

    public function getAll(User $user, int $storeId, int $businessId)
    {
        $hasAccess = $this->permissionRepository->isStoreInBusinessOwnedBy($user->id, $storeId)
            || $this->permissionRepository->getUserRoleOnStore($user->id, $storeId) !== null;

        if (!$hasAccess) {
            throw new AuthorizationException('You do not have access to this store.');
        }

        return $this->supplierRepository->all($businessId);
    }

    public function getById(int $id): Supplier
    {
        return $this->mustFind($id);
    }

    public function create(User $actor, int $storeId, int $businessId, array $data): Supplier
    {
        $supplier = DB::transaction(function () use ($storeId, $businessId, $data) {
            $party = $this->partyRepository->create(PartyTypeEnum::SUPPLIER);
            return $this->supplierRepository->create(array_merge($data, [
                'party_id'    => $party->id,
                'business_id' => $businessId,
                'store_id'    => $storeId,
            ]));
        });

        $this->auditLogService->supplierCreated($actor, $storeId, $businessId, $supplier);

        return $supplier;
    }

    public function update(User $actor, int $storeId, int $businessId, int $id, array $data): Supplier
    {
        $supplier = $this->mustFind($id);
        $supplier = $this->supplierRepository->update($supplier, $data);

        $this->auditLogService->supplierUpdated($actor, $storeId, $businessId, $supplier);

        return $supplier;
    }

    public function deleteMany(User $actor, int $businessId, ?int $storeId, array $ids): int
    {
        $this->assertScopedAccess($actor, $businessId, $storeId);

        if (empty($ids)) {
            return 0;
        }

        return DB::transaction(function () use ($actor, $businessId, $storeId, $ids) {
            $suppliers = $this->supplierRepository
                ->listQuery($businessId, $storeId, null, $ids)
                ->lockForUpdate()
                ->get();

            if ($suppliers->isEmpty()) {
                return 0;
            }

            $snapshots = $suppliers->map(fn ($s) => [
                'id'       => (int) $s->id,
                'name'     => (string) $s->name,
                'store_id' => $s->store_id !== null ? (int) $s->store_id : null,
                'party_id' => (int) $s->party_id,
            ])->all();

            $supplierIds = array_column($snapshots, 'id');
            $partyIds    = array_column($snapshots, 'party_id');
            $expected    = count($snapshots);

            $deletedSuppliers = $this->supplierRepository->deleteMany($supplierIds);
            if ($deletedSuppliers !== $expected) {
                throw new SupplierException(
                    ErrorCode::SERVER_ERROR,
                    "Bulk supplier delete affected {$deletedSuppliers} rows; expected {$expected}."
                );
            }

            $deletedParties = $this->partyRepository->deleteMany($partyIds);
            if ($deletedParties !== $expected) {
                throw new SupplierException(
                    ErrorCode::SERVER_ERROR,
                    "Bulk party delete affected {$deletedParties} rows; expected {$expected}."
                );
            }

            foreach ($snapshots as $snap) {
                $this->auditLogService->supplierDeleted(
                    $actor,
                    $snap['store_id'],
                    $businessId,
                    $snap['id'],
                    $snap['name']
                );
            }

            return $expected;
        });
    }

    public function delete(User $actor, int $storeId, int $businessId, int $id): void
    {
        $supplier = $this->mustFind($id);
        $supplierId = $supplier->id;
        $supplierName = $supplier->name;

        DB::transaction(function () use ($supplier) {
            $partyId = $supplier->party_id;
            $this->supplierRepository->delete($supplier);
            $this->partyRepository->delete($partyId);
        });

        $this->auditLogService->supplierDeleted($actor, $storeId, $businessId, $supplierId, $supplierName);
    }

    private function mustFind(int $id): Supplier
    {
        $supplier = $this->supplierRepository->findById($id);
        if (!$supplier) {
            throw new SupplierException(ErrorCode::SUPPLIER_NOT_FOUND, 'Supplier not found.');
        }
        return $supplier;
    }

    public function queueExport(User $user, int $businessId, array $filters = [], ?string $clientId = null): Export
    {
        $this->assertScopedAccess($user, $businessId, $filters['store_id'] ?? null);

        $normalizedFilters = $this->normalizeExportFilters($filters);
        $filterSignature   = $this->filterSignature($normalizedFilters);
        $type              = ExportSupplierJob::TYPE;

        $inProgress = $this->exportRepository->findInProgressDuplicate($user->id, $type, $businessId, $filterSignature, $clientId);
        if ($inProgress) {
            return $inProgress;
        }

        $reusable = $this->exportRepository->findCompletedDuplicateWithFile($user->id, $type, $businessId, $filterSignature, $clientId);
        if ($reusable) {
            return $reusable;
        }

        $existing = $this->exportRepository->findExistingFilesForScope($user->id, $type, $businessId, $clientId);
        foreach ($existing as $old) {
            $this->exportService->deleteFile($old);
        }

        $business = Business::find($businessId);

        $export = $this->exportService->createPending(
            $user,
            $type,
            [
                'scope'            => 'business',
                'scope_id'         => $businessId,
                'scope_name'       => $business?->name,
                'filters'          => $normalizedFilters,
                'filter_signature' => $filterSignature,
                'client_id'        => $clientId,
            ]
        );

        ExportSupplierJob::dispatch($export->id);

        return $export;
    }

    private function assertScopedAccess(User $user, int $businessId, $storeId): void
    {
        if ($storeId !== null && $storeId !== '') {
            $storeId = (int) $storeId;
            $hasAccess = $this->permissionRepository->isStoreInBusinessOwnedBy($user->id, $storeId)
                || $this->permissionRepository->getUserRoleOnStore($user->id, $storeId) !== null;
            if (!$hasAccess) {
                throw new AuthorizationException('You do not have access to this store.');
            }
            return;
        }

        if (!$this->permissionRepository->isBusinessOwner($user->id, $businessId)) {
            throw new AuthorizationException('You do not have access to this business.');
        }
    }

    private function normalizeExportFilters(array $filters): array
    {
        $clean = [];

        if (!empty($filters['store_id'])) {
            $clean['store_id'] = (string) $filters['store_id'];
        }
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

        return $clean;
    }

    private function filterSignature(array $filters): string
    {
        ksort($filters);
        return sha1((string) json_encode($filters));
    }
}
