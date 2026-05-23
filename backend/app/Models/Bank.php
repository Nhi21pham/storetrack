<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bank extends Model
{
    protected $fillable = [
        'short_name',
        'full_name_vi',
        'full_name_en',
        'short_name_normalized',
        'full_name_vi_normalized',
        'full_name_en_normalized',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }
}
