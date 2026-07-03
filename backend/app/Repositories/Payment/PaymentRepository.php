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

    public function update(Payment $payment, array $data): Payment
    {
        $payment->update($data);
        return $payment;
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

    /** A party's payments across every store in a business — owner business view. */
    public function allForPartyInBusiness(int $businessId, int $partyId): Collection
    {
        return Payment::with(self::RELATIONS)
            ->where('party_id', $partyId)
            ->whereHas('store', fn ($q) => $q->where('business_id', $businessId))
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
