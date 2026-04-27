<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\PartyTypeEnum;
use App\Exceptions\CustomerException;
use App\Models\Customer;
use App\Repositories\PartyRepository;
use App\Repositories\CustomerRepository;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public function __construct(
        private CustomerRepository $customerRepository,
        private PartyRepository $partyRepository
    ) {}

    public function getAll()
    {
        return $this->customerRepository->all();
    }

    public function getById(int $id): Customer
    {
        return $this->mustFind($id);
    }

    public function create(array $data): Customer
    {
        return DB::transaction(function () use ($data) {
            $party = $this->partyRepository->create(PartyTypeEnum::CUSTOMER);
            return $this->customerRepository->create(array_merge($data, [
                'party_id' => $party->id,
            ]));
        });
    }

    public function update(int $id, array $data): Customer
    {
        $customer = $this->mustFind($id);
        return $this->customerRepository->update($customer, $data);
    }

    public function delete(int $id): void
    {
        $customer = $this->mustFind($id);

        DB::transaction(function () use ($customer) {
            $partyId = $customer->party_id;
            $this->customerRepository->delete($customer);
            $this->partyRepository->delete($partyId);
        });
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
