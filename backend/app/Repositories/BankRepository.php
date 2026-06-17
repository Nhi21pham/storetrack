<?php

namespace App\Repositories;

use App\Models\Bank;
use App\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class BankRepository
{
    public function findById(int $id): ?Bank
    {
        return Bank::find($id);
    }

    public function findByShortNameNormalized(int $businessId, string $normalized, ?int $excludeId = null): ?Bank
    {
        $query = Bank::query()
            ->where('business_id', $businessId)
            ->where('short_name_normalized', $normalized);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->first();
    }

    public function findByFullNameViNormalized(int $businessId, string $normalized, ?int $excludeId = null): ?Bank
    {
        $query = Bank::query()
            ->where('business_id', $businessId)
            ->where('full_name_vi_normalized', $normalized);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->first();
    }

    public function findByFullNameEnNormalized(int $businessId, string $normalized, ?int $excludeId = null): ?Bank
    {
        $query = Bank::query()
            ->where('business_id', $businessId)
            ->where('full_name_en_normalized', $normalized);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->first();
    }

    /**
     * Every bank in the business (active or not) with its id and three
     * normalized names, for resolving and duplicate-checking rows during
     * import. Covers all three unique indexes, so inactive banks count as taken
     * too.
     *
     * @return list<array{id: int, short: string, vi: string, en: string}>
     */
    public function allNormalizedNames(int $businessId): array
    {
        return Bank::query()
            ->where('business_id', $businessId)
            ->get(['id', 'short_name_normalized', 'full_name_vi_normalized', 'full_name_en_normalized'])
            ->map(fn (Bank $bank) => [
                'id'    => (int) $bank->id,
                'short' => (string) $bank->short_name_normalized,
                'vi'    => (string) $bank->full_name_vi_normalized,
                'en'    => (string) $bank->full_name_en_normalized,
            ])
            ->all();
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

    public function all(int $businessId, bool $includeInactive = false): Collection
    {
        $query = Bank::query()->where('business_id', $businessId);
        if (!$includeInactive) {
            $query->where('is_active', true);
        }
        return $query->orderByDesc('id')->get();
    }

    public function listQuery(int $businessId, ?string $search = null, ?array $ids = null, bool $includeInactive = true): Builder
    {
        $query = Bank::query()->where('business_id', $businessId);

        if ($ids !== null && count($ids) > 0) {
            $query->whereIn('id', $ids);
        }

        if (!$includeInactive) {
            $query->where('is_active', true);
        }

        if ($search !== null && $search !== '') {
            $needle = TextNormalizer::normalize($search);
            if ($needle !== '') {
                $like = '%'.$needle.'%';
                $query->where(function (Builder $q) use ($like) {
                    $q->where('short_name_normalized', 'like', $like)
                        ->orWhere('full_name_vi_normalized', 'like', $like)
                        ->orWhere('full_name_en_normalized', 'like', $like);
                });
            }
        }

        return $query->orderBy('id');
    }

    public function searchQuery(int $businessId, string $needle, bool $includeInactive = false, ?int $limit = null): Builder
    {
        $query = Bank::query()->where('business_id', $businessId);
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
