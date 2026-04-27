<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'party_id',
        'name',
        'email',
        'phone',
        'address',
        'tax_code',
    ];

    public function party()
    {
        return $this->belongsTo(Party::class);
    }
}
