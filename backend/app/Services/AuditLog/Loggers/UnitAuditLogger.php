<?php

namespace App\Services\AuditLog\Loggers;

use App\Enums\AuditAction;
use App\Enums\AuditObjectType;
use App\Models\Store;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLog\AuditLogger;

class UnitAuditLogger extends AuditLogger
{
    public function unitCreated(User $actor, Unit $unit): void
    {
        $storeId = (int) $unit->store_id;
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::UNIT, AuditAction::CREATED,
            self::actor($actor) . " has CREATED unit {$unit->name}.",
            [
                'unit_id'     => $unit->id,
                'name'        => $unit->name,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }

    public function unitUpdated(User $actor, Unit $unit): void
    {
        $storeId = (int) $unit->store_id;
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::UNIT, AuditAction::UPDATED,
            self::actor($actor) . " has UPDATED unit {$unit->name}.",
            [
                'unit_id'     => $unit->id,
                'name'        => $unit->name,
                'is_active'   => (bool) $unit->is_active,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }

    public function unitDeactivated(User $actor, Unit $unit): void
    {
        $storeId = (int) $unit->store_id;
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::UNIT, AuditAction::DEACTIVATED,
            self::actor($actor) . " has DEACTIVATED unit {$unit->name}.",
            [
                'unit_id'     => $unit->id,
                'name'        => $unit->name,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }

    public function unitReactivated(User $actor, Unit $unit): void
    {
        $storeId = (int) $unit->store_id;
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::UNIT, AuditAction::REACTIVATED,
            self::actor($actor) . " has REACTIVATED unit {$unit->name}.",
            [
                'unit_id'     => $unit->id,
                'name'        => $unit->name,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }

    public function unitDeleted(User $actor, int $unitId, string $name, int $storeId): void
    {
        $store = Store::find($storeId);
        $businessId = $store?->business_id;
        $this->log($storeId, $actor, AuditObjectType::UNIT, AuditAction::DELETED,
            self::actor($actor) . " has DELETED unit {$name}.",
            [
                'unit_id'     => $unitId,
                'name'        => $name,
                'store_id'    => $storeId,
                'store_name'  => $store?->name,
                'business_id' => $businessId,
            ],
            $businessId
        );
    }
}
