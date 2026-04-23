<?php

namespace App\Models;

use App\Casts\NormalizedEmail;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    protected $fillable = [
        'store_id',
        'actor_id',
        'actor_name',
        'actor_email',
        'object_type',
        'action',
        'message',
        'metadata',
    ];

    protected $casts = [
        'actor_email' => NormalizedEmail::class,
        'metadata'    => 'array',
    ];
}
