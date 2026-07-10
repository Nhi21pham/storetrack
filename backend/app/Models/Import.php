<?php

namespace App\Models;

use App\Enums\ImportTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Import extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'original_filename',
        'disk',
        'path',
        'total_rows',
        'processed_count',
        'created_count',
        'skipped_count',
        'failed_count',
        'metadata',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'type' => ImportTypeEnum::class,
        'metadata' => 'array',
        'completed_at' => 'datetime',
        'total_rows' => 'integer',
        'processed_count' => 'integer',
        'created_count' => 'integer',
        'skipped_count' => 'integer',
        'failed_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
