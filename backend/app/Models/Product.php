<?php

namespace App\Models;

use App\Models\Invoice\InventoryBatch;
use App\Models\Invoice\ProductStock;
use App\Models\Tag\Taggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Product extends Model
{
    protected $fillable = [
        'store_id',
        'product_category_id',
        'unit_id',
        'code',
        'name',
        'name_normalized',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function taggables(): MorphMany
    {
        return $this->morphMany(Taggable::class, 'taggable');
    }

    public function stock(): HasOne
    {
        return $this->hasOne(ProductStock::class);
    }

    public function inventoryBatches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class);
    }

    public function getTagsAttribute(): array
    {
        if (!$this->relationLoaded('taggables')) {
            $this->load(['taggables.tag', 'taggables.tagValue']);
        }
        return $this->taggables->map(fn (Taggable $t) => [
            'tag_id'       => (string) $t->tag_id,
            'tag_name'     => $t->tag?->name,
            'tag_value_id' => $t->tag_value_id !== null ? (string) $t->tag_value_id : null,
            'value'        => $t->tagValue?->value,
        ])->all();
    }
}
