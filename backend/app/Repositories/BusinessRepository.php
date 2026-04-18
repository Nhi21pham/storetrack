<?php

namespace App\Repositories;

use App\Models\Business;

class BusinessRepository
{
    public function create(array $data): Business
    {
        return Business::create($data);
    }

    public function findById(int $id): ?Business
    {
        return Business::find($id);
    }

    public function update(Business $business, array $data): Business
    {
        $business->update($data);
        return $business->fresh();
    }

    public function delete(Business $business): void
    {
        $business->delete();
    }
}