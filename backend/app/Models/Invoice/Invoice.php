<?php

namespace App\Models\Invoice;

use App\Enums\InvoicePaymentMethodEnum;
use App\Enums\InvoicePaymentStatusEnum;
use App\Enums\InvoiceTypeEnum;
use App\Models\Party;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'store_id',
        'type',
        'code',
        'party_id',
        'created_by',
        'description',
        'invoice_date',
        'payment_method',
        'payment_status',
        'subtotal',
        'tax_total',
        'grand_total',
    ];

    protected $casts = [
        'type'           => InvoiceTypeEnum::class,
        'payment_method' => InvoicePaymentMethodEnum::class,
        'payment_status' => InvoicePaymentStatusEnum::class,
        'invoice_date'   => 'date',
        'subtotal'       => 'decimal:2',
        'tax_total'      => 'decimal:2',
        'grand_total'    => 'decimal:2',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceProduct::class);
    }

    public function getPartyNameAttribute(): ?string
    {
        if (!$this->party) {
            return null;
        }
        return $this->type === InvoiceTypeEnum::PURCHASE
            ? $this->party->supplier?->name
            : $this->party->customer?->name;
    }
}
