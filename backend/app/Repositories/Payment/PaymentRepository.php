<?php

namespace App\Repositories\Payment;

use App\Models\Payment\Payment;
use Illuminate\Database\Eloquent\Collection;

class PaymentRepository
{
    private const RELATIONS = ['party.customer', 'party.supplier', 'creator', 'allocations.invoice'];

    public function create(array $data): Payment
    {
        return Payment::create($data);
    }

    public function findById(int $id): ?Payment
    {
        return Payment::with(self::RELATIONS)->find($id);
    }

    /** Newest-first, for a party's payment history in a store. */
    public function allForParty(int $storeId, int $partyId): Collection
    {
        return Payment::with(self::RELATIONS)
            ->where('store_id', $storeId)
            ->where('party_id', $partyId)
            ->orderByDesc('id')
            ->get();
    }

    public function lockById(int $id): ?Payment
    {
        return Payment::whereKey($id)->lockForUpdate()->first();
    }

    public function delete(Payment $payment): void
    {
        $payment->delete();
    }
}
