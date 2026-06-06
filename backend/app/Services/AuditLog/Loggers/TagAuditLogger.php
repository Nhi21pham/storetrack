<?php

namespace App\Services\AuditLog\Loggers;

use App\Enums\AuditAction;
use App\Enums\AuditObjectType;
use App\Models\Export;
use App\Models\Store;
use App\Models\Tag\Tag;
use App\Models\User;
use App\Services\AuditLog\AuditLogger;

class TagAuditLogger extends AuditLogger
{
    public function tagCreated(User $actor, Tag $tag): void
    {
        $storeId = (int) $tag->store_id;
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::TAG, AuditAction::CREATED,
            self::actor($actor) . " has CREATED tag {$tag->name}.",
            [
                'tag_id'      => $tag->id,
                'name'        => $tag->name,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }

    public function tagUpdated(User $actor, Tag $tag): void
    {
        $storeId = (int) $tag->store_id;
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::TAG, AuditAction::UPDATED,
            self::actor($actor) . " has UPDATED tag {$tag->name}.",
            [
                'tag_id'      => $tag->id,
                'name'        => $tag->name,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }

    public function tagKeyDeleted(User $actor, int $tagId, string $name, int $storeId): void
    {
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::TAG, AuditAction::DELETED,
            self::actor($actor) . " has DELETED tag {$name}.",
            [
                'tag_id'      => $tagId,
                'name'        => $name,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }

    public function tagExported(User $actor, int $storeId, Export $export, string $scopeName): void
    {
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $storeName = $store?->name ?? $scopeName;
        $metadata = $export->metadata ?? [];

        $this->log($storeId, $actor, AuditObjectType::TAG, AuditAction::EXPORTED,
            self::actor($actor) . " has EXPORTED tags of store {$storeName}.",
            [
                'store_id'    => $storeId,
                'store_name'  => $storeName,
                'business_id' => $businessId,
                'export_id'   => $export->id,
                'filename'    => $export->filename,
                'filters'     => $metadata['filters'] ?? null,
            ],
            $businessId
        );
    }
}
