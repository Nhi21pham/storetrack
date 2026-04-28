<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\PartyTypeEnum;
use App\Exceptions\SupplierException;
use App\Exceptions\AuthorizationException;
use App\Models\Supplier;
use App\Models\User;
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
        private PermissionRepository $permissionRepository
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
}
