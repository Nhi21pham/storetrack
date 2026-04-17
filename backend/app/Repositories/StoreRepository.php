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

    public function update(Store $store, array $data): Store
    {
        $store->update($data);
        return $store->fresh();
    }
}