<?php

namespace App\Repositories\Invoice;

use App\Models\Invoice\InventoryBatch;

class InventoryBatchRepository
{
    public function create(array $data): InventoryBatch
    {
        return InventoryBatch::create($data);
    }
}
