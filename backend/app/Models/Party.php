<?php

namespace App\Models;

use App\Enums\PartyTypeEnum;
use Illuminate\Database\Eloquent\Model;

class Party extends Model
{
    protected $fillable = ['type'];

    protected $casts = [
        'type' => PartyTypeEnum::class,
    ];

    public function supplier()
    {
        return $this->hasOne(Supplier::class);
    }

    public function customer()
    {
        return $this->hasOne(Customer::class);
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function business()
    {
        return $this->hasOne(Business::class);
    }

    public function store()
    {
        return $this->hasOne(Store::class);
    }
}
