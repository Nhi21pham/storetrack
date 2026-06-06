<?php

namespace App\Models\Invoice;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceProductCost extends Model
{
    protected $fillable = [
        'invoice_product_id',
        'inventory_batch_id',
        'quantity',
        'unit_cost',
    ];

    protected $casts = [
        'quantity'  => 'decimal:3',
        'unit_cost' => 'decimal:2',
    ];

    public function invoiceProduct(): BelongsTo
    {
        return $this->belongsTo(InvoiceProduct::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'inventory_batch_id');
    }
}
