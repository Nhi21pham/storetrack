<?php

namespace App\Repositories;

use App\Models\Store;

class StoreRepository
{
    public function create(array $data): Store
    {
        return Store::create($data);
    }

    public function findById(int $id): ?Store
    {
        return Store::find($id);
    }

    public function businessIdFor(int $storeId): ?int
    {
        $businessId = Store::whereKey($storeId)->value('business_id');
        return $businessId !== null ? (int) $businessId : null;
    }

    public function update(Store $store, array $data): Store
    {
        $store->update($data);
        return $store->fresh();
    }
}