<?php

namespace App\Services\AuditLog\Loggers;

use App\Enums\AuditAction;
use App\Enums\AuditObjectType;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Export;
use App\Models\Store;
use App\Models\User;
use App\Services\AuditLog\AuditLogger;

class BusinessAuditLogger extends AuditLogger
{
    public function businessCreated(User $actor, Business $business, Store $store, \DateTimeInterface $createdAt): void
    {
        $entry = new AuditLog([
            'store_id'    => $store->id,
            'business_id' => $business->id,
            'actor_id'    => $actor->id,
            'actor_name'  => $actor->name,
            'actor_email' => $actor->email,
            'object_type' => AuditObjectType::BUSINESS->value,
            'action'      => AuditAction::CREATED->value,
            'message'     => self::actor($actor) . " has CREATED business {$business->name}.",
            'metadata'    => [
                'business_id'   => $business->id,
                'business_name' => $business->name,
                'store_id'      => $store->id,
                'store_name'    => $store->name,
            ],
        ]);
        $entry->created_at = $createdAt;
        $entry->save();
    }

    public function businessUpdated(User $actor, Business $business): void
    {
        $stores = Store::where('business_id', $business->id)->get();
        foreach ($stores as $store) {
            $this->log(
                $store->id, $actor, AuditObjectType::BUSINESS, AuditAction::UPDATED,
                self::actor($actor) . " has UPDATED business {$business->name}.",
                [
                    'business_id'   => $business->id,
                    'business_name' => $business->name,
                    'store_id'      => $store->id,
                    'store_name'    => $store->name,
                ],
                $business->id
            );
        }
    }

    public function auditLogExported(User $user, int $businessId, string $businessName, Export $export): void
    {
        $metadata = $export->metadata ?? [];
        $business = Business::find($businessId);
        $this->log(
            null,
            $user,
            AuditObjectType::BUSINESS,
            AuditAction::EXPORTED,
            "{$user->name}({$user->email}) has EXPORTED audit log of business {$businessName}.",
            [
                'business_id'   => $businessId,
                'business_name' => $businessName,
                'export_id'     => $export->id,
                'filename'      => $export->filename,
                'filters'       => $metadata['filters'] ?? null,
            ],
            $business?->id
        );
    }
}
