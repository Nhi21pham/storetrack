<?php

namespace App\Repositories;

use App\Models\Supplier;

class SupplierRepository
{
    public function create(array $data): Supplier
    {
        return Supplier::create($data);
    }

    public function findById(int $id): ?Supplier
    {
        return Supplier::find($id);
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);
        return $supplier->fresh();
    }

    public function delete(Supplier $supplier): void
    {
        $supplier->delete();
    }

    public function all()
    {
        return Supplier::with('party')->latest()->get();
    }
}
