<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\PartyTypeEnum;
use App\Exceptions\SupplierException;
use App\Models\Supplier;
use App\Repositories\PartyRepository;
use App\Repositories\SupplierRepository;
use Illuminate\Support\Facades\DB;

class SupplierService
{
    public function __construct(
        private SupplierRepository $supplierRepository,
        private PartyRepository $partyRepository
    ) {}

    public function getAll()
    {
        return $this->supplierRepository->all();
    }

    public function getById(int $id): Supplier
    {
        return $this->mustFind($id);
    }

    public function create(array $data): Supplier
    {
        return DB::transaction(function () use ($data) {
            $party = $this->partyRepository->create(PartyTypeEnum::SUPPLIER);
            return $this->supplierRepository->create(array_merge($data, [
                'party_id' => $party->id,
            ]));
        });
    }

    public function update(int $id, array $data): Supplier
    {
        $supplier = $this->mustFind($id);
        return $this->supplierRepository->update($supplier, $data);
    }

    public function delete(int $id): void
    {
        $supplier = $this->mustFind($id);

        DB::transaction(function () use ($supplier) {
            $partyId = $supplier->party_id;
            $this->supplierRepository->delete($supplier);
            $this->partyRepository->delete($partyId);
        });
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
