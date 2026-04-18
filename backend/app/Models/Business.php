<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    protected $fillable = [
        'owner_id',
        'name',
        'tax_code',
        'address',
        'email',
        'phone',
    ];

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