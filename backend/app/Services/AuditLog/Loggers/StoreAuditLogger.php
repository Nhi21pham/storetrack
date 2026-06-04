<?php

namespace App\Services\AuditLog\Loggers;

use App\Enums\AuditAction;
use App\Enums\AuditObjectType;
use App\Models\Export;
use App\Models\Store;
use App\Models\User;
use App\Services\AuditLog\AuditLogger;

class StoreAuditLogger extends AuditLogger
{
    public function storeCreated(User $actor, Store $store): void
    {
        $this->log(
            $store->id, $actor, AuditObjectType::STORE, AuditAction::CREATED,
            self::actor($actor) . " has CREATED store {$store->name}.",
            [
                'store_id'      => $store->id,
                'store_name'    => $store->name,
                'business_id'   => $store->business_id,
            ],
            $store->business_id
        );
    }

    public function storeUpdated(User $actor, Store $store): void
    {
        $this->log(
            $store->id, $actor, AuditObjectType::STORE, AuditAction::UPDATED,
            self::actor($actor) . " has UPDATED store {$store->name}.",
            [
                'store_id'      => $store->id,
                'store_name'    => $store->name,
                'business_id'   => $store->business_id,
            ],
            $store->business_id
        );
    }

    public function storeDeactivated(User $actor, Store $store): void
    {
        $this->log(
            $store->id, $actor, AuditObjectType::STORE, AuditAction::DEACTIVATED,
            self::actor($actor) . " has DEACTIVATED store {$store->name}.",
            [
                'store_id'      => $store->id,
                'store_name'    => $store->name,
                'business_id'   => $store->business_id,
            ],
            $store->business_id
        );
    }

    public function storeReactivated(User $actor, Store $store): void
    {
        $this->log(
            $store->id, $actor, AuditObjectType::STORE, AuditAction::REACTIVATED,
            self::actor($actor) . " has REACTIVATED store {$store->name}.",
            [
                'store_id'      => $store->id,
                'store_name'    => $store->name,
                'business_id'   => $store->business_id,
            ],
            $store->business_id
        );
    }

    public function storeDeleted(User $actor, Store $store): void
    {
        $this->log(
            $store->id, $actor, AuditObjectType::STORE, AuditAction::DELETED,
            self::actor($actor) . " has DELETED store {$store->name}.",
            [
                'store_id'      => $store->id,
                'store_name'    => $store->name,
                'business_id'   => $store->business_id,
            ],
            $store->business_id
        );
    }

    public function auditLogExported(User $user, int $storeId, string $storeName, Export $export): void
    {
        $metadata = $export->metadata ?? [];
        $store = Store::find($storeId);
        $this->log(
            $storeId,
            $user,
            AuditObjectType::STORE,
            AuditAction::EXPORTED,
            "{$user->name}({$user->email}) has EXPORTED audit log of store {$storeName}.",
            [
                'store_id'    => $storeId,
                'store_name'  => $storeName,
                'business_id' => $store?->business_id,
                'export_id'   => $export->id,
                'filename'    => $export->filename,
                'filters'     => $metadata['filters'] ?? null,
            ],
            $store?->business_id
        );
    }
}
