<?php

namespace App\Models\Tag;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Taggable extends Model
{
    protected $fillable = [
        'store_id',
        'tag_id',
        'tag_value_id',
        'taggable_type',
        'taggable_id',
    ];

    public function taggable(): MorphTo
    {
        return $this->morphTo();
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }

    public function tagValue(): BelongsTo
    {
        return $this->belongsTo(TagValue::class);
    }
}
