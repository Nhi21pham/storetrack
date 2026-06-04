<?php

namespace App\Services\AuditLog\Loggers;

use App\Enums\AuditAction;
use App\Enums\AuditObjectType;
use App\Models\Invitation;
use App\Models\Store;
use App\Models\User;
use App\Services\AuditLog\AuditLogger;

class InvitationAuditLogger extends AuditLogger
{
    public function invitationSent(User $actor, Store $store, string $inviteeEmail, string $role): void
    {
        $this->log(
            $store->id, $actor, AuditObjectType::INVITATION, AuditAction::INVITED,
            self::actor($actor) . " has INVITED {$inviteeEmail} as " . ucfirst(strtolower($role)) . ".",
            [
                'invitee_email' => $inviteeEmail,
                'role'          => $role,
                'store_id'      => $store->id,
                'store_name'    => $store->name,
                'business_id'   => $store->business_id,
            ],
            $store->business_id
        );
    }

    public function invitationCancelled(User $actor, Invitation $invitation): void
    {
        $store = Store::find($invitation->store_id);
        $this->log(
            $invitation->store_id, $actor, AuditObjectType::INVITATION, AuditAction::CANCELLED,
            self::actor($actor) . " has CANCELLED invitation for {$invitation->invitee_email}.",
            [
                'invitee_email' => $invitation->invitee_email,
                'store_id'      => $invitation->store_id,
                'store_name'    => $store?->name,
                'business_id'   => $store?->business_id,
            ],
            $store?->business_id
        );
    }

    public function invitationAccepted(User $invitee, Invitation $invitation): void
    {
        $role = is_string($invitation->role) ? $invitation->role : $invitation->role->value;
        $store = Store::find($invitation->store_id);
        $this->log(
            $invitation->store_id, $invitee, AuditObjectType::INVITATION, AuditAction::ACCEPTED,
            "{$invitee->name}({$invitee->email}) has ACCEPTED the invitation as " . ucfirst(strtolower($role)) . ".",
            [
                'invitee_email' => $invitee->email,
                'role'          => $role,
                'store_id'      => $invitation->store_id,
                'store_name'    => $store?->name,
                'business_id'   => $store?->business_id,
            ],
            $store?->business_id
        );
    }

    public function invitationDeclined(User $invitee, Invitation $invitation): void
    {
        $store = Store::find($invitation->store_id);
        $this->log(
            $invitation->store_id, $invitee, AuditObjectType::INVITATION, AuditAction::DECLINED,
            "{$invitee->name}({$invitee->email}) has DECLINED the invitation.",
            [
                'invitee_email' => $invitee->email,
                'store_id'      => $invitation->store_id,
                'store_name'    => $store?->name,
                'business_id'   => $store?->business_id,
            ],
            $store?->business_id
        );
    }
}
