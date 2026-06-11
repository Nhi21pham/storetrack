<?php

namespace App\Models\Invoice;

use App\Models\Tax;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceProductTax extends Model
{
    protected $fillable = [
        'invoice_product_id',
        'tax_id',
        'tax_name',
        'tax_rate',
        'tax_amount',
    ];

    protected $casts = [
        'tax_rate'   => 'decimal:4',
        'tax_amount' => 'decimal:2',
    ];

    public function invoiceProduct(): BelongsTo
    {
        return $this->belongsTo(InvoiceProduct::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }
}
