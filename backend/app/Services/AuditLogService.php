<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditObjectType;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Invitation;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Exceptions\AuthorizationException;
use App\Repositories\PermissionRepository;

class AuditLogService
{
    public function __construct(private PermissionRepository $permissionRepository) {}

    public function getStoreLogs(User $user, int $storeId, int $page = 1, int $perPage = 20, ?string $startDate = null, ?string $endDate = null): array
    {
        $hasAccess = $this->permissionRepository->isStoreInBusinessOwnedBy($user->id, $storeId)
            || $this->permissionRepository->getUserRoleOnStore($user->id, $storeId) !== null;

        if (!$hasAccess) {
            throw new AuthorizationException('You do not have access to this store.');
        }

        $query = AuditLog::where('store_id', $storeId);

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $paginator = $query->orderByDesc('created_at')
            ->paginate(min($perPage, 100), ['*'], 'page', max($page, 1));

        return [
            'data'         => $paginator->items(),
            'total'        => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
        ];
    }

    public function getBusinessLogs(User $user, int $businessId, int $page = 1, int $perPage = 20, ?string $startDate = null, ?string $endDate = null): array
    {
        if (!$this->permissionRepository->isBusinessOwner($user->id, $businessId)) {
            throw new AuthorizationException('You do not have access to this business.');
        }

        $query = AuditLog::where('business_id', $businessId);

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $paginator = $query->orderByDesc('created_at')
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

    private static function actor(User $user): string
    {
        return "{$user->name}({$user->email})";
    }

    // Business actions

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

    // Store actions

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

    // User assignment actions

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

    // Invitation actions

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
        $businessId = Store::find($invitation->store_id)?->business_id;
        $this->log(
            $invitation->store_id, $actor, AuditObjectType::INVITATION, AuditAction::CANCELLED,
            self::actor($actor) . " has CANCELLED invitation for {$invitation->invitee_email}.",
            [
                'invitee_email' => $invitation->invitee_email,
                'store_id'      => $invitation->store_id,
                'business_id'   => $businessId,
            ],
            $businessId
        );
    }

    public function invitationAccepted(User $invitee, Invitation $invitation): void
    {
        $role = is_string($invitation->role) ? $invitation->role : $invitation->role->value;
        $businessId = Store::find($invitation->store_id)?->business_id;
        $this->log(
            $invitation->store_id, $invitee, AuditObjectType::INVITATION, AuditAction::ACCEPTED,
            "{$invitee->name}({$invitee->email}) has ACCEPTED the invitation as " . ucfirst(strtolower($role)) . ".",
            [
                'invitee_email' => $invitee->email,
                'role'          => $role,
                'store_id'      => $invitation->store_id,
                'business_id'   => $businessId,
            ],
            $businessId
        );
    }

    public function invitationDeclined(User $invitee, Invitation $invitation): void
    {
        $businessId = Store::find($invitation->store_id)?->business_id;
        $this->log(
            $invitation->store_id, $invitee, AuditObjectType::INVITATION, AuditAction::DECLINED,
            "{$invitee->name}({$invitee->email}) has DECLINED the invitation.",
            [
                'invitee_email' => $invitee->email,
                'store_id'      => $invitation->store_id,
                'business_id'   => $businessId,
            ],
            $businessId
        );
    }

    // Supplier actions

    public function supplierCreated(User $actor, int $storeId, int $businessId, Supplier $supplier): void
    {
        $this->log($storeId, $actor, AuditObjectType::SUPPLIER, AuditAction::CREATED,
            self::actor($actor) . " has CREATED supplier {$supplier->name}.",
            [
                'supplier_id'   => $supplier->id,
                'supplier_name' => $supplier->name,
                'business_id'   => $businessId,
                'created_from'  => $storeId,
            ],
            $businessId
        );
    }

    public function supplierUpdated(User $actor, int $storeId, int $businessId, Supplier $supplier): void
    {
        $this->log($storeId, $actor, AuditObjectType::SUPPLIER, AuditAction::UPDATED,
            self::actor($actor) . " has UPDATED supplier {$supplier->name}.",
            [
                'supplier_id'    => $supplier->id,
                'supplier_name'  => $supplier->name,
                'business_id'    => $businessId,
                'updated_from'   => $storeId,
            ],
            $businessId
        );
    }

    public function supplierDeleted(User $actor, int $storeId, int $businessId, int $supplierId, string $supplierName): void
    {
        $this->log($storeId, $actor, AuditObjectType::SUPPLIER, AuditAction::REMOVED,
            self::actor($actor) . " has DELETED supplier {$supplierName}.",
            [
                'supplier_id'   => $supplierId,
                'supplier_name' => $supplierName,
                'business_id'   => $businessId,
                'deleted_from'  => $storeId,
            ],
            $businessId
        );
    }

    // Customer actions

    public function customerCreated(User $actor, int $storeId, int $businessId, Customer $customer): void
    {
        $this->log($storeId, $actor, AuditObjectType::CUSTOMER, AuditAction::CREATED,
            self::actor($actor) . " has CREATED customer {$customer->name}.",
            [
                'customer_id'   => $customer->id,
                'customer_name' => $customer->name,
                'business_id'   => $businessId,
                'created_from'  => $storeId,
            ],
            $businessId
        );
    }

    public function customerUpdated(User $actor, int $storeId, int $businessId, Customer $customer): void
    {
        $this->log($storeId, $actor, AuditObjectType::CUSTOMER, AuditAction::UPDATED,
            self::actor($actor) . " has UPDATED customer {$customer->name}.",
            [
                'customer_id'   => $customer->id,
                'customer_name' => $customer->name,
                'business_id'   => $businessId,
                'updated_from'  => $storeId,
            ],
            $businessId
        );
    }

    public function customerDeleted(User $actor, int $storeId, int $businessId, int $customerId, string $customerName): void
    {
        $this->log($storeId, $actor, AuditObjectType::CUSTOMER, AuditAction::REMOVED,
            self::actor($actor) . " has DELETED customer {$customerName}.",
            [
                'customer_id'   => $customerId,
                'customer_name' => $customerName,
                'business_id'   => $businessId,
                'deleted_from'  => $storeId,
            ],
            $businessId
        );
    }
}
