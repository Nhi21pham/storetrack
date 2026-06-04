<?php

namespace App\Services\AuditLog\Loggers;

use App\Enums\AuditAction;
use App\Enums\AuditObjectType;
use App\Models\Store;
use App\Models\Tag\Tag;
use App\Models\Tag\TagValue;
use App\Models\User;
use App\Services\AuditLog\AuditLogger;

class TagValueAuditLogger extends AuditLogger
{
    public function tagValueCreated(User $actor, Tag $tag, TagValue $value): void
    {
        $storeId = (int) $tag->store_id;
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::TAG, AuditAction::CREATED,
            self::actor($actor) . " has ADDED value {$value->value} to tag {$tag->name}.",
            [
                'tag_id'       => $tag->id,
                'name'         => $tag->name,
                'tag_value_id' => $value->id,
                'value'        => $value->value,
                'store_id'     => $storeId,
                'store_name'   => $store?->name,
                'business_id'  => $businessId,
            ],
            $businessId
        );
    }

    public function tagValueUpdated(User $actor, Tag $tag, TagValue $value): void
    {
        $storeId = (int) $tag->store_id;
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::TAG, AuditAction::UPDATED,
            self::actor($actor) . " has UPDATED value {$value->value} of tag {$tag->name}.",
            [
                'tag_id'       => $tag->id,
                'name'         => $tag->name,
                'tag_value_id' => $value->id,
                'value'        => $value->value,
                'store_id'     => $storeId,
                'store_name'   => $store?->name,
                'business_id'  => $businessId,
            ],
            $businessId
        );
    }

    public function tagValueDeleted(User $actor, int $storeId, string $tagName, int $valueId, string $value): void
    {
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::TAG, AuditAction::DELETED,
            self::actor($actor) . " has DELETED value {$value} from tag {$tagName}.",
            [
                'tag_name'     => $tagName,
                'tag_value_id' => $valueId,
                'value'        => $value,
                'store_id'     => $storeId,
                'store_name'   => $store?->name,
                'business_id'  => $businessId,
            ],
            $businessId
        );
    }
}
