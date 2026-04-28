<?php

namespace App\Repositories;

use App\Models\Customer;

class CustomerRepository
{
    public function create(array $data): Customer
    {
        return Customer::create($data);
    }

    public function findById(int $id): ?Customer
    {
        return Customer::find($id);
    }

    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);
        return $customer->fresh();
    }

    public function delete(Customer $customer): void
    {
        $customer->delete();
    }

    public function all(int $businessId)
    {
        return Customer::with('party')->where('business_id', $businessId)->latest()->get();
    }
}
