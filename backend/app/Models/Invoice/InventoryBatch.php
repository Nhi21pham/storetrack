<?php

namespace App\Models\Invoice;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryBatch extends Model
{
    protected $fillable = [
        'store_id',
        'product_id',
        'source_invoice_id',
        'source_invoice_product_id',
        'unit_cost',
        'quantity_received',
        'quantity_remaining',
        'received_at',
    ];

    protected $casts = [
        'unit_cost'          => 'decimal:2',
        'quantity_received'  => 'decimal:3',
        'quantity_remaining' => 'decimal:3',
        'received_at'        => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sourceInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'source_invoice_id');
    }

    public function sourceInvoiceProduct(): BelongsTo
    {
        return $this->belongsTo(InvoiceProduct::class, 'source_invoice_product_id');
    }

    public function costs(): HasMany
    {
        return $this->hasMany(InvoiceProductCost::class);
    }
}
