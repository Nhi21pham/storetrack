<?php

namespace App\Models;

use App\Casts\NormalizedEmail;
use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    protected $fillable = [
        'party_id',
        'owner_id',
        'name',
        'tax_code',
        'address',
        'email',
        'phone',
    ];

    protected $casts = [
        'email' => NormalizedEmail::class,
    ];

    public function party()
    {
        return $this->belongsTo(Party::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function stores()
    {
        return $this->hasMany(Store::class);
    }

    public function activeStores()
    {
        return $this->hasMany(Store::class)->where('is_active', true);
    }
}