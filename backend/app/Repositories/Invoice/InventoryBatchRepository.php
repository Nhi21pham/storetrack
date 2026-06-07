<?php

namespace App\Repositories\Invoice;

use App\Models\Invoice\InventoryBatch;
use Illuminate\Database\Eloquent\Collection;

class InventoryBatchRepository
{
    public function create(array $data): InventoryBatch
    {
        return InventoryBatch::create($data);
    }

    /** All batches a given invoice produced, locked for update (for reversal). */
    public function lockBySourceInvoice(int $invoiceId): Collection
    {
        return InventoryBatch::where('source_invoice_id', $invoiceId)
            ->lockForUpdate()
            ->get();
    }

    public function delete(InventoryBatch $batch): void
    {
        $batch->delete();
    }
}
