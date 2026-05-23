<?php

namespace App\Repositories;

use App\Models\Bank;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class BankRepository
{
    public function findById(int $id): ?Bank
    {
        return Bank::find($id);
    }

    public function findByShortNameNormalized(string $normalized, ?int $excludeId = null): ?Bank
    {
        $query = Bank::query()->where('short_name_normalized', $normalized);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->first();
    }

    public function findByFullNameViNormalized(string $normalized, ?int $excludeId = null): ?Bank
    {
        $query = Bank::query()->where('full_name_vi_normalized', $normalized);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->first();
    }

    public function findByFullNameEnNormalized(string $normalized, ?int $excludeId = null): ?Bank
    {
        $query = Bank::query()->where('full_name_en_normalized', $normalized);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->first();
    }

    public function create(array $data): Bank
    {
        return Bank::create($data);
    }

    public function update(Bank $bank, array $data): Bank
    {
        $bank->update($data);
        return $bank->fresh();
    }

    public function setActive(Bank $bank, bool $isActive): Bank
    {
        $bank->update(['is_active' => $isActive]);
        return $bank->fresh();
    }

    public function delete(Bank $bank): void
    {
        $bank->delete();
    }

    public function hasBankAccounts(int $bankId): bool
    {
        return Bank::where('id', $bankId)->whereHas('bankAccounts')->exists();
    }

    public function all(bool $includeInactive = false): Collection
    {
        $query = Bank::query();
        if (!$includeInactive) {
            $query->where('is_active', true);
        }
        return $query->orderBy('short_name')->get();
    }

    public function searchQuery(string $needle, bool $includeInactive = false, ?int $limit = null): Builder
    {
        $query = Bank::query();
        if (!$includeInactive) {
            $query->where('is_active', true);
        }
        $like = '%' . $needle . '%';
        $query->where(function ($q) use ($like) {
            $q->where('short_name_normalized', 'like', $like)
                ->orWhere('full_name_vi_normalized', 'like', $like)
                ->orWhere('full_name_en_normalized', 'like', $like);
        });
        $query->orderBy('short_name');
        if ($limit !== null) {
            $query->limit($limit);
        }
        return $query;
    }
}
