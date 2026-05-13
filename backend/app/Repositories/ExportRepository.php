<?php

namespace App\Repositories;

use App\Models\Export;
use Illuminate\Support\Collection;

class ExportRepository
{
    public function create(array $attributes): Export
    {
        return Export::create($attributes);
    }

    public function find(int $id): ?Export
    {
        return Export::find($id);
    }

    public function findForUser(int $id, int $userId): ?Export
    {
        return Export::where('id', $id)->where('user_id', $userId)->first();
    }

    public function update(Export $export, array $attributes): Export
    {
        $export->update($attributes);

        return $export->refresh();
    }

    /**
     * Exports that still have a file recorded but should be cleaned up:
     * either failed, or past their expiry.
     *
     * @return Collection<int, Export>
     */
    public function staleWithFiles(): Collection
    {
        return Export::query()
            ->whereNotNull('path')
            ->whereNotNull('disk')
            ->where(function ($q) {
                $q->where('status', Export::STATUS_FAILED)
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('expires_at')
                            ->where('expires_at', '<=', now());
                    });
            })
            ->get();
    }
}
