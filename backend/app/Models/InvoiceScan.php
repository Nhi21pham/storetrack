<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceScan extends Model
{
    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    // Invoice kind this scan belongs to.
    public const TYPE_PURCHASE = 'purchase';

    public const TYPE_SALE = 'sale';

    // Engine that produced the scan.
    public const SCAN_TYPE_AI = 'ai';

    public const SCAN_TYPE_TEMPLATE = 'template';

    protected $fillable = [
        'user_id',
        'store_id',
        'type',
        'scan_type',
        'status',
        'original_filename',
        'metadata',
        'error_message',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
