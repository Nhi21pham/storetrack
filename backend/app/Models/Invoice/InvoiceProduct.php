<?php

namespace App\Models\Invoice;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceProduct extends Model
{
    protected $fillable = [
        'invoice_id',
        'product_id',
        'product_name',
        'quantity',
        'unit_price',
        'subtotal',
        'tax_total',
        'grand_total',
    ];

    protected $casts = [
        'quantity'    => 'decimal:3',
        'unit_price'  => 'decimal:2',
        'subtotal'    => 'decimal:2',
        'tax_total'   => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(InvoiceProductTax::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(InvoiceProductCost::class);
    }
}
