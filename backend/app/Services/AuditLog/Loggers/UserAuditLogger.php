<?php

namespace App\Services\AuditLog\Loggers;

use App\Enums\AuditAction;
use App\Enums\AuditObjectType;
use App\Models\Store;
use App\Models\User;
use App\Services\AuditLog\AuditLogger;

class UserAuditLogger extends AuditLogger
{
    public function userAssigned(User $actor, Store $store, User $target, string $role): void
    {
        $this->log(
            $store->id, $actor, AuditObjectType::USER, AuditAction::ASSIGNED,
            self::actor($actor) . " has ASSIGNED {$target->name}({$target->email}) as " . ucfirst(strtolower($role)) . ".",
            [
                'user_id'       => $target->id,
                'user_name'     => $target->name,
                'user_email'    => $target->email,
                'role'          => $role,
                'store_id'      => $store->id,
                'store_name'    => $store->name,
                'business_id'   => $store->business_id,
            ],
            $store->business_id
        );
    }

    public function userRoleUpdated(User $actor, Store $store, User $target, string $oldRole, string $newRole): void
    {
        $this->log(
            $store->id, $actor, AuditObjectType::USER, AuditAction::ROLE_CHANGED,
            self::actor($actor) . " has UPDATED role of {$target->name}({$target->email}) from " . ucfirst(strtolower($oldRole)) . " to " . ucfirst(strtolower($newRole)) . ".",
            [
                'user_id'       => $target->id,
                'user_name'     => $target->name,
                'user_email'    => $target->email,
                'old_role'      => $oldRole,
                'new_role'      => $newRole,
                'store_id'      => $store->id,
                'store_name'    => $store->name,
                'business_id'   => $store->business_id,
            ],
            $store->business_id
        );
    }

    public function userRemoved(User $actor, Store $store, User $target): void
    {
        $this->log(
            $store->id, $actor, AuditObjectType::USER, AuditAction::REMOVED,
            self::actor($actor) . " has REMOVED {$target->name}({$target->email}) from the store.",
            [
                'user_id'       => $target->id,
                'user_name'     => $target->name,
                'user_email'    => $target->email,
                'store_id'      => $store->id,
                'store_name'    => $store->name,
                'business_id'   => $store->business_id,
            ],
            $store->business_id
        );
    }
}
