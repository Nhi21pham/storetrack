<?php

namespace App\Models;

use App\Casts\NormalizedEmail;
use App\Enums\InvitationStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    protected $table = 'store_invitations';

    protected $fillable = [
        'store_id',
        'inviter_id',
        'invitee_email',
        'role',
        'token',
        'status',
        'expires_at',
        'accepted_at',
    ];

    protected $casts = [
        'invitee_email' => NormalizedEmail::class,
        'expires_at'    => 'datetime',
        'accepted_at'   => 'datetime',
        'status'        => InvitationStatusEnum::class,
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function isPending(): bool
    {
        return $this->status === InvitationStatusEnum::Pending;
    }

    public function isExpired(): bool
    {
        return $this->status === InvitationStatusEnum::Expired;
    }

    public function isTimedOut(): bool
    {
        return $this->expires_at->isPast();
    }
}
