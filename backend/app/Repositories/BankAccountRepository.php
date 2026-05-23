<?php

namespace App\Repositories;

use App\Models\BankAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class BankAccountRepository
{
    public function findById(int $id): ?BankAccount
    {
        return BankAccount::with(['bank', 'province', 'party'])->find($id);
    }

    public function findByBankAndNumber(int $bankId, string $accountNumber, ?int $excludeId = null): ?BankAccount
    {
        $query = BankAccount::query()
            ->where('bank_id', $bankId)
            ->where('account_number', $accountNumber);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->first();
    }

    public function create(array $data): BankAccount
    {
        $account = BankAccount::create($data);
        return $account->fresh(['bank', 'province', 'party']);
    }

    public function update(BankAccount $account, array $data): BankAccount
    {
        $account->update($data);
        return $account->fresh(['bank', 'province', 'party']);
    }

    public function delete(BankAccount $account): void
    {
        $account->delete();
    }

    public function listForParty(int $partyId): Collection
    {
        return BankAccount::with(['bank', 'province'])
            ->where('party_id', $partyId)
            ->orderBy('id')
            ->get();
    }

    public function businessScopedQuery(int $businessId, ?string $search = null): Builder
    {
        $query = BankAccount::query()
            ->with(['bank', 'province', 'party'])
            ->whereHas('party', function ($q) use ($businessId) {
                $q->where(function ($inner) use ($businessId) {
                    $inner->whereHas('supplier', fn ($s) => $s->where('business_id', $businessId))
                        ->orWhereHas('customer', fn ($c) => $c->where('business_id', $businessId))
                        ->orWhereHas('business', fn ($b) => $b->where('id', $businessId));
                });
            });

        if ($search !== null && $search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('account_number', 'like', $like)
                    ->orWhere('account_holder_name', 'like', $like)
                    ->orWhere('branch', 'like', $like);
            });
        }

        return $query->orderBy('id', 'desc');
    }
}
