<?php

namespace App\Repositories\Invoice;

use App\Enums\InvoiceTypeEnum;
use App\Models\Invoice\InvoiceSequence;
use Illuminate\Support\Facades\DB;

class InvoiceSequenceRepository
{
    /**
     * Atomically allocate the next per-(store, type) invoice number.
     * insertOrIgnore creates the counter row if missing (race-safe), then
     * lockForUpdate serialises concurrent allocations for that store+type.
     */
    public function nextNumber(int $storeId, InvoiceTypeEnum $type): int
    {
        DB::table('invoice_sequences')->insertOrIgnore([
            'store_id'      => $storeId,
            'type'          => $type->value,
            'last_sequence' => 0,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $sequence = InvoiceSequence::query()
            ->where('store_id', $storeId)
            ->where('type', $type->value)
            ->lockForUpdate()
            ->first();

        $next = (int) $sequence->last_sequence + 1;
        $sequence->update(['last_sequence' => $next]);

        return $next;
    }
}
