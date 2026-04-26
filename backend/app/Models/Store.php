<?php

namespace App\Models;

use App\Casts\NormalizedEmail;
use App\Enums\RoleEnum;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    protected $fillable = [
        'party_id',
        'business_id',
        'name',
        'address',
        'email',
        'phone',
        'is_active',
        'deactivated_at',
    ];
    protected $attributes = [
    'is_active' => true,
    ];
    protected $casts = [
        'email'          => NormalizedEmail::class,
        'is_active'      => 'boolean',
        'deactivated_at' => 'datetime',
    ];

    public function party()
    {
        return $this->belongsTo(Party::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function roleFor(User $user): ?RoleEnum
    {
        $pivot = $this->users()->where('user_id', $user->id)->first()?->pivot;
        return $pivot ? RoleEnum::from($pivot->role) : null;
    }
}