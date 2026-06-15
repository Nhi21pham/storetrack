<?php

namespace App\Models;

use App\Enums\PartyTypeEnum;
use App\Models\Invoice\Invoice;
use App\Models\Payment\Payment;
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

    public function bankAccounts()
    {
        return $this->hasMany(BankAccount::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
