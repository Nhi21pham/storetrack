<?php

namespace App\Services\AuditLog;

use App\Enums\AuditAction;
use App\Enums\AuditObjectType;
use App\Models\AuditLog;
use App\Models\User;

abstract class AuditLogger
{
    protected function log(
        ?int $storeId,
        ?User $actor,
        AuditObjectType $objectType,
        AuditAction $action,
        string $message,
        array $metadata = [],
        ?int $businessId = null
    ): void {
        AuditLog::create([
            'store_id'    => $storeId,
            'business_id' => $businessId,
            'actor_id'    => $actor?->id,
            'actor_name'  => $actor?->name,
            'actor_email' => $actor?->email,
            'object_type' => $objectType->value,
            'action'      => $action->value,
            'message'     => $message,
            'metadata'    => $metadata ?: null,
        ]);
    }

    protected static function actor(User $user): string
    {
        return "{$user->name}({$user->email})";
    }
}
