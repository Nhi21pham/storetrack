<?php

namespace App\Models;

use App\Casts\NormalizedEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    protected $fillable = [
        'store_id',
        'business_id',
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

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function getStoreNameAttribute(): ?string
    {
        return $this->store?->name ?? ($this->metadata['store_name'] ?? null);
    }

    /** Structured metadata as a JSON string, for the GraphQL API to expose. */
    public function getMetadataJsonAttribute(): ?string
    {
        return $this->metadata ? json_encode($this->metadata, JSON_UNESCAPED_UNICODE) : null;
    }
}
