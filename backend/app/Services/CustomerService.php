<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\PartyTypeEnum;
use App\Exceptions\CustomerException;
use App\Models\Customer;
use App\Models\User;
use App\Repositories\PartyRepository;
use App\Repositories\CustomerRepository;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public function __construct(
        private CustomerRepository $customerRepository,
        private PartyRepository $partyRepository,
        private AuditLogService $auditLogService
    ) {}

    public function getAll()
    {
        return $this->customerRepository->all();
    }

    public function getById(int $id): Customer
    {
        return $this->mustFind($id);
    }

    public function create(User $actor, int $storeId, int $businessId, array $data): Customer
    {
        $customer = DB::transaction(function () use ($businessId, $data) {
            $party = $this->partyRepository->create(PartyTypeEnum::CUSTOMER);
            return $this->customerRepository->create(array_merge($data, [
                'party_id'    => $party->id,
                'business_id' => $businessId,
            ]));
        });

        $this->auditLogService->customerCreated($actor, $storeId, $businessId, $customer);

        return $customer;
    }

    public function update(User $actor, int $storeId, int $businessId, int $id, array $data): Customer
    {
        $customer = $this->mustFind($id);
        $customer = $this->customerRepository->update($customer, $data);

        $this->auditLogService->customerUpdated($actor, $storeId, $businessId, $customer);

        return $customer;
    }

    public function delete(User $actor, int $storeId, int $businessId, int $id): void
    {
        $customer = $this->mustFind($id);
        $customerId = $customer->id;
        $customerName = $customer->name;

        DB::transaction(function () use ($customer) {
            $partyId = $customer->party_id;
            $this->customerRepository->delete($customer);
            $this->partyRepository->delete($partyId);
        });

        $this->auditLogService->customerDeleted($actor, $storeId, $businessId, $customerId, $customerName);
    }

    private function mustFind(int $id): Customer
    {
        $customer = $this->customerRepository->findById($id);
        if (!$customer) {
            throw new CustomerException(ErrorCode::CUSTOMER_NOT_FOUND, 'Customer not found.');
        }
        return $customer;
    }
}
