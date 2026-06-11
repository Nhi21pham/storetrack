<?php

namespace App\Services\AuditLog\Loggers;

use App\Enums\AuditAction;
use App\Enums\AuditObjectType;
use App\Models\Store;
use App\Models\Tax;
use App\Models\User;
use App\Services\AuditLog\AuditLogger;

class TaxAuditLogger extends AuditLogger
{
    public function taxCreated(User $actor, Tax $tax): void
    {
        $storeId = (int) $tax->store_id;
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::TAX, AuditAction::CREATED,
            self::actor($actor) . " has CREATED tax {$tax->name}.",
            [
                'tax_id'      => $tax->id,
                'name'        => $tax->name,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }

    public function taxUpdated(User $actor, Tax $tax): void
    {
        $storeId = (int) $tax->store_id;
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::TAX, AuditAction::UPDATED,
            self::actor($actor) . " has UPDATED tax {$tax->name}.",
            [
                'tax_id'      => $tax->id,
                'name'        => $tax->name,
                'is_active'   => (bool) $tax->is_active,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }

    public function taxDeactivated(User $actor, Tax $tax): void
    {
        $storeId = (int) $tax->store_id;
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::TAX, AuditAction::DEACTIVATED,
            self::actor($actor) . " has DEACTIVATED tax {$tax->name}.",
            [
                'tax_id'      => $tax->id,
                'name'        => $tax->name,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }

    public function taxReactivated(User $actor, Tax $tax): void
    {
        $storeId = (int) $tax->store_id;
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::TAX, AuditAction::REACTIVATED,
            self::actor($actor) . " has REACTIVATED tax {$tax->name}.",
            [
                'tax_id'      => $tax->id,
                'name'        => $tax->name,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }

    public function taxDeleted(User $actor, int $taxId, string $name, int $storeId): void
    {
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::TAX, AuditAction::DELETED,
            self::actor($actor) . " has DELETED tax {$name}.",
            [
                'tax_id'      => $taxId,
                'name'        => $name,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }
}
