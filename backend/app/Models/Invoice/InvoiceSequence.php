<?php

namespace App\Models\Invoice;

use App\Enums\InvoiceTypeEnum;
use App\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceSequence extends Model
{
    protected $fillable = [
        'store_id',
        'type',
        'last_sequence',
    ];

    protected $casts = [
        'type'          => InvoiceTypeEnum::class,
        'last_sequence' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
