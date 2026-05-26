<?php

namespace App\Repositories;

use App\Models\Province;
use Illuminate\Database\Eloquent\Collection;

class ProvinceRepository
{
    public function all(): Collection
    {
        return Province::query()->orderBy('name_vi')->get();
    }

    public function findById(int $id): ?Province
    {
        return Province::find($id);
    }
}
