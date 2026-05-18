<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Supplier extends Model
{
    protected $fillable = [
        'party_id',
        'business_id',
        'store_id',
        'name',
        'email',
        'phone',
        'address',
        'tax_code',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'supplier_stores')->withTimestamps();
    }
}
