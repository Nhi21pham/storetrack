<?php

namespace App\Repositories;

use App\Enums\PartyTypeEnum;
use App\Models\Party;

class PartyRepository
{
    public function create(PartyTypeEnum $type): Party
    {
        return Party::create(['type' => $type->value]);
    }

    /** Party with the customer/supplier records needed to resolve its debt ledger + business. */
    public function findWithLedgerRelations(int $id): ?Party
    {
        return Party::with([
            'customer:id,party_id,business_id',
            'supplier:id,party_id,business_id',
        ])->find($id);
    }

    public function delete(int $id): void
    {
        Party::destroy($id);
    }

    public function deleteMany(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }
        return Party::whereIn('id', array_unique($ids))->delete();
    }
}
