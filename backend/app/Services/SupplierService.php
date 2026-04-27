<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\PartyTypeEnum;
use App\Exceptions\SupplierException;
use App\Models\Supplier;
use App\Models\User;
use App\Repositories\PartyRepository;
use App\Repositories\SupplierRepository;
use Illuminate\Support\Facades\DB;

class SupplierService
{
    public function __construct(
        private SupplierRepository $supplierRepository,
        private PartyRepository $partyRepository,
        private AuditLogService $auditLogService
    ) {}

    public function getAll()
    {
        return $this->supplierRepository->all();
    }

    public function getById(int $id): Supplier
    {
        return $this->mustFind($id);
    }

    public function create(User $actor, int $storeId, int $businessId, array $data): Supplier
    {
        $supplier = DB::transaction(function () use ($businessId, $data) {
            $party = $this->partyRepository->create(PartyTypeEnum::SUPPLIER);
            return $this->supplierRepository->create(array_merge($data, [
                'party_id'    => $party->id,
                'business_id' => $businessId,
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
