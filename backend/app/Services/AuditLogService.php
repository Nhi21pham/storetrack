<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditObjectType;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Invitation;
use App\Models\Store;
use App\Models\User;
use App\Exceptions\AuthorizationException;
use App\Repositories\PermissionRepository;

class AuditLogService
{
    public function __construct(private PermissionRepository $permissionRepository) {}

    public function getStoreLogs(User $user, int $storeId, int $page = 1, int $perPage = 20): array
    {
        $hasAccess = $this->permissionRepository->isStoreInBusinessOwnedBy($user->id, $storeId)
            || $this->permissionRepository->getUserRoleOnStore($user->id, $storeId) !== null;

        if (!$hasAccess) {
            throw new AuthorizationException('You do not have access to this store.');
        }

        $paginator = AuditLog::where('store_id', $storeId)
            ->orderByDesc('created_at')
            ->paginate(min($perPage, 100), ['*'], 'page', max($page, 1));

        return [
            'data'         => $paginator->items(),
            'total'        => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
        ];
    }

    public function log(
        ?int $storeId,
        ?User $actor,
        AuditObjectType $objectType,
        AuditAction $action,
        string $message,
        array $metadata = []
    ): void {
        AuditLog::create([
            'store_id'    => $storeId,
            'actor_id'    => $actor?->id,
            'actor_name'  => $actor?->name,
            'actor_email' => $actor?->email,
            'object_type' => $objectType->value,
            'action'      => $action->value,
            'message'     => $message,
            'metadata'    => $metadata ?: null,
        ]);
    }

    private static function actor(User $user): string
    {
        return "{$user->name}({$user->email})";
    }

    // Business actions

    public function businessCreated(User $actor, Business $business, Store $store, \DateTimeInterface $createdAt): void
    {
        $entry = new AuditLog([
            'store_id'    => $store->id,
            'actor_id'    => $actor->id,
            'actor_name'  => $actor->name,
            'actor_email' => $actor->email,
            'object_type' => AuditObjectType::BUSINESS->value,
            'action'      => AuditAction::CREATED->value,
            'message'     => self::actor($actor) . " has CREATED business {$business->name}.",
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
                self::actor($actor) . " has UPDATED business {$business->name}."
            );
        }
    }

    // Store actions

    public function storeCreated(User $actor, Store $store): void
    {
        $this->log(
            $store->id, $actor, AuditObjectType::STORE, AuditAction::CREATED,
            self::actor($actor) . " has CREATED store {$store->name}."
        );
    }

    public function storeUpdated(User $actor, Store $store): void
    {
        $this->log(
            $store->id, $actor, AuditObjectType::STORE, AuditAction::UPDATED,
            self::actor($actor) . " has UPDATED store {$store->name}."
        );
    }

    public function storeDeactivated(User $actor, Store $store): void
    {
        $this->log(
            $store->id, $actor, AuditObjectType::STORE, AuditAction::DEACTIVATED,
            self::actor($actor) . " has DEACTIVATED store {$store->name}."
        );
    }

    public function storeReactivated(User $actor, Store $store): void
    {
        $this->log(
            $store->id, $actor, AuditObjectType::STORE, AuditAction::REACTIVATED,
            self::actor($actor) . " has REACTIVATED store {$store->name}."
        );
    }

    // User assignment actions

    public function userAssigned(User $actor, Store $store, User $target, string $role): void
    {
        $this->log(
            $store->id, $actor, AuditObjectType::USER, AuditAction::ASSIGNED,
            self::actor($actor) . " has ASSIGNED {$target->name}({$target->email}) as " . ucfirst(strtolower($role)) . "."
        );
    }

    public function userRoleUpdated(User $actor, Store $store, User $target, string $oldRole, string $newRole): void
    {
        $this->log(
            $store->id, $actor, AuditObjectType::USER, AuditAction::ROLE_CHANGED,
            self::actor($actor) . " has UPDATED role of {$target->name}({$target->email}) from " . ucfirst(strtolower($oldRole)) . " to " . ucfirst(strtolower($newRole)) . "."
        );
    }

    public function userRemoved(User $actor, Store $store, User $target): void
    {
        $this->log(
            $store->id, $actor, AuditObjectType::USER, AuditAction::REMOVED,
            self::actor($actor) . " has REMOVED {$target->name}({$target->email}) from the store."
        );
    }

    // Invitation actions

    public function invitationSent(User $actor, Store $store, string $inviteeEmail, string $role): void
    {
        $this->log(
            $store->id, $actor, AuditObjectType::INVITATION, AuditAction::INVITED,
            self::actor($actor) . " has INVITED {$inviteeEmail} as " . ucfirst(strtolower($role)) . "."
        );
    }

    public function invitationCancelled(User $actor, Invitation $invitation): void
    {
        $this->log(
            $invitation->store_id, $actor, AuditObjectType::INVITATION, AuditAction::CANCELLED,
            self::actor($actor) . " has CANCELLED invitation for {$invitation->invitee_email}."
        );
    }

    public function invitationAccepted(User $invitee, Invitation $invitation): void
    {
        $role = is_string($invitation->role) ? $invitation->role : $invitation->role->value;
        $this->log(
            $invitation->store_id, $invitee, AuditObjectType::INVITATION, AuditAction::ACCEPTED,
            "{$invitee->name}({$invitee->email}) has ACCEPTED the invitation as " . ucfirst(strtolower($role)) . "."
        );
    }

    public function invitationDeclined(User $invitee, Invitation $invitation): void
    {
        $this->log(
            $invitation->store_id, $invitee, AuditObjectType::INVITATION, AuditAction::DECLINED,
            "{$invitee->name}({$invitee->email}) has DECLINED the invitation."
        );
    }
}
