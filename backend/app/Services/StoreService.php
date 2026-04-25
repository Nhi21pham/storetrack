<?php

namespace App\Services;

use App\Enums\ErrorCode;
use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\Store;
use App\Models\User;
use App\Repositories\StoreRepository;
use Illuminate\Support\Facades\DB;
use App\Exceptions\StoreException;

class StoreService
{
    public function __construct(
        private StoreRepository $storeRepository,
        private PermissionService $permissionService,
        private AuditLogService $auditLogService
    ) {}

    public function createStore(User $user, array $data): Store
    {
        $this->permissionService->authorizeBusiness(
            $user,
            PermissionEnum::CREATE_STORE,
            (int) $data['business_id']
        );

        $store = DB::transaction(function () use ($user, $data) {
            $store = $this->storeRepository->create(array_merge($data, ['is_active' => true]));
            $store->users()->attach($user->id, ['role' => RoleEnum::OWNER->value]);
            return $store;
        });

        $this->auditLogService->storeCreated($user, $store);

        $business = $store->business;
        $this->auditLogService->businessCreated($user, $business, $store, $business->created_at);

        return $store;
    }

    public function updateStore(User $user, int $storeId, array $data): Store
    {
        $this->permissionService->authorizeStore($user, PermissionEnum::UPDATE_STORE, $storeId);
        $store = $this->mustFind($storeId);
        $store = $this->storeRepository->update($store, $data);

        $this->auditLogService->storeUpdated($user, $store);

        return $store;
    }

    public function deactivateStore(User $user, int $storeId): Store
    {
        $this->permissionService->authorizeStore($user, PermissionEnum::DEACTIVATE_STORE, $storeId);
        $store = $this->mustFind($storeId);

        $store = $this->storeRepository->update($store, [
            'is_active' => false,
            'deactivated_at' => now(),
        ]);

        $this->auditLogService->storeDeactivated($user, $store);

        return $store;
    }

    public function reactivateStore(User $user, int $storeId): Store
    {
        $this->permissionService->authorizeStore($user, PermissionEnum::REACTIVATE_STORE, $storeId);
        $store = $this->mustFind($storeId);

        $store = $this->storeRepository->update($store, [
            'is_active' => true,
            'deactivated_at' => null,
        ]);

        $this->auditLogService->storeReactivated($user, $store);

        return $store;
    }

    public function deleteStore(User $user, int $storeId): void
    {
        $this->permissionService->authorizeStore($user, PermissionEnum::DEACTIVATE_STORE, $storeId);
        $store = $this->mustFind($storeId);

        DB::transaction(function () use ($store) {
            $store->users()->detach();
            $store->delete();
        });
    }

    public function assignUser(User $actor, int $storeId, int $userId, RoleEnum $role): Store
    {
        $this->permissionService->authorizeStore($actor, PermissionEnum::ASSIGN_STORE_USER, $storeId);
        $store = $this->mustFind($storeId);

        if ($store->business->owner_id === $userId && $role !== RoleEnum::OWNER) {
            throw new StoreException(ErrorCode::CANNOT_CHANGE_OWNER_ROLE, 'Cannot change the role of the business owner.');
        }

        $existingMember = $store->users()->where('user_id', $userId)->first();
        $oldRole = $existingMember?->pivot->role;

        DB::transaction(function () use ($store, $userId, $role) {
            $store->users()->syncWithoutDetaching([
                $userId => ['role' => $role->value],
            ]);
        });

        $result = $store->fresh('users');

        $target = User::find($userId);
        if ($target) {
            if (!$existingMember) {
                $this->auditLogService->userAssigned($actor, $store, $target, $role->value);
            } elseif ($oldRole !== $role->value) {
                $this->auditLogService->userRoleUpdated($actor, $store, $target, $oldRole, $role->value);
            }
        }

        return $result;
    }

    public function removeUser(User $actor, int $storeId, int $userId): Store
    {
        $this->permissionService->authorizeStore($actor, PermissionEnum::REMOVE_STORE_USER, $storeId);
        $store = $this->mustFind($storeId);

        if ($store->business->owner_id === $userId) {
            throw new StoreException(ErrorCode::CANNOT_REMOVE_OWNER, 'Cannot remove the business owner from their own store.');
        }

        $target = User::find($userId);
        $store->users()->detach($userId);

        if ($target) {
            $this->auditLogService->userRemoved($actor, $store, $target);
        }

        return $store->fresh('users');
    }

    public function getUserStores(User $user)
    {
        return $user->stores()->with('business')->latest()->get();
    }

    private function mustFind(int $id): Store
    {
        $store = $this->storeRepository->findById($id);
        if (!$store) {
            throw new StoreException(ErrorCode::STORE_NOT_FOUND, 'Store not found.');
        }
        return $store;
    }

    public function getAccessibleStores(User $user): array
    {
        $ownedStores = $this->getOwnedStores($user);
        $assignedStores = $this->getAssignedStores($user);

        return array_merge($ownedStores, $assignedStores);
    }

    private function getOwnedStores(User $user): array
    {
        $businesses = $user->businesses()->with(['stores' => fn($q) => $q->with('users')->latest()])->latest()->get();
        $result = [];

        foreach ($businesses as $business) {
            foreach ($business->stores as $store) {
                $result[] = [
                    'id' => (string) $store->id,
                    'name' => $store->name,
                    'address' => $store->address,
                    'email' => $store->email,
                    'phone' => $store->phone,
                    'is_active' => $store->is_active,
                    'my_role' => 'owner',
                    'business' => [
                        'id' => (string) $business->id,
                        'name' => $business->name,
                    ],
                    'users' => $store->users->map(fn($u) => [
                        'id' => (string) $u->id,
                        'name' => $u->name,
                        'email' => $u->email,
                        'role' => $u->pivot->role,
                    ])->toArray(),
                ];
            }
        }

        return $result;
    }

    private function getAssignedStores(User $user): array
    {
        $stores = $user->stores()
            ->with(['business', 'users'])
            ->latest()
            ->get();

        $result = [];

        foreach ($stores as $store) {
            if ($store->business->owner_id === $user->id) continue;

            $result[] = [
                'id' => (string) $store->id,
                'name' => $store->name,
                'address' => $store->address,
                'email' => $store->email,
                'phone' => $store->phone,
                'is_active' => $store->is_active,
                'my_role' => $store->pivot->role,
                'business' => [
                    'id' => (string) $store->business->id,
                    'name' => $store->business->name,
                ],
                'users' => $store->users->map(fn($u) => [
                    'id' => (string) $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->pivot->role,
                ])->toArray(),
            ];
        }

        return $result;
    }
    public function getStore(User $user, int $storeId): Store
    {
        $store = $this->mustFind($storeId);

        $role = $this->permissionService->getUserRoleOnStore($user, $storeId);
        $isOwner = $store->business->owner_id === $user->id;

        if (!$role && !$isOwner) {
            throw new StoreException(ErrorCode::STORE_NOT_FOUND, 'Store not found.');
        }

        return $store;
    }
}