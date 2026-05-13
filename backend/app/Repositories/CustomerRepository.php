<?php

namespace App\Repositories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;

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

    public function listQuery(int $businessId, ?int $storeId = null, ?string $search = null): Builder
    {
        $query = Customer::query()->where('business_id', $businessId);

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        if ($search !== null && $search !== '') {
            $needle = '%'.$search.'%';
            $query->where(function ($q) use ($needle) {
                $q->where('name', 'like', $needle)
                    ->orWhere('email', 'like', $needle)
                    ->orWhere('tax_code', 'like', $needle)
                    ->orWhere('address', 'like', $needle)
                    ->orWhere('phone', 'like', $needle);
            });
        }

        return $query->orderBy('id');
    }
}
