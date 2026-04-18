<?php

namespace App\Services;

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\Store;
use App\Models\User;
use App\Repositories\StoreRepository;
use Illuminate\Support\Facades\DB;

class StoreService
{
    public function __construct(
        private StoreRepository $storeRepository,
        private PermissionService $permissionService
    ) {}

    public function createStore(User $user, array $data): Store
    {
        $this->permissionService->authorizeBusiness(
            $user,
            PermissionEnum::CreateStore,
            (int) $data['business_id']
        );

        return DB::transaction(function () use ($user, $data) {
            $store = $this->storeRepository->create(array_merge($data, ['is_active' => true]));
            $store->users()->attach($user->id, ['role' => RoleEnum::Owner->value]);
            return $store;
        });
    }

    public function updateStore(User $user, int $storeId, array $data): Store
    {
        $this->permissionService->authorizeStore($user, PermissionEnum::UpdateStore, $storeId);
        $store = $this->mustFind($storeId);
        return $this->storeRepository->update($store, $data);
    }

    public function deactivateStore(User $user, int $storeId): Store
    {
        $this->permissionService->authorizeStore($user, PermissionEnum::DeactivateStore, $storeId);
        $store = $this->mustFind($storeId);

        return $this->storeRepository->update($store, [
            'is_active' => false,
            'deactivated_at' => now(),
        ]);
    }
    public function reactivateStore(User $user, int $storeId): Store
    {
        $this->permissionService->authorizeStore($user, PermissionEnum::ReactivateStore, $storeId);
        $store = $this->mustFind($storeId);

        return $this->storeRepository->update($store, [
            'is_active' => true,
            'deactivated_at' => null,
        ]);
    }
    public function deleteStore(User $user, int $storeId): void
    {
        $this->permissionService->authorizeStore($user, PermissionEnum::DeactivateStore, $storeId);
        $store = $this->mustFind($storeId);

        DB::transaction(function () use ($store) {
            $store->users()->detach();
            $store->delete();
        });
    }
    public function assignUser(User $actor, int $storeId, int $userId, RoleEnum $role): Store
    {
        $this->permissionService->authorizeStore($actor, PermissionEnum::AssignStoreUser, $storeId);
        $store = $this->mustFind($storeId);

        if ($store->business->owner_id === $userId && $role !== RoleEnum::Owner) {
            throw new \Exception('Cannot change the role of the business owner.');
        }

        DB::transaction(function () use ($store, $userId, $role) {
            $store->users()->syncWithoutDetaching([
                $userId => ['role' => $role->value],
            ]);
        });

        return $store->fresh('users');
    }

    public function removeUser(User $actor, int $storeId, int $userId): Store
    {
        $this->permissionService->authorizeStore($actor, PermissionEnum::RemoveStoreUser, $storeId);
        $store = $this->mustFind($storeId);

        if ($store->business->owner_id === $userId) {
            throw new \Exception('Cannot remove the business owner from their own store.');
        }

        $store->users()->detach($userId);
        return $store->fresh('users');
    }

    public function getUserStores(User $user)
    {
        return $user->stores()->with('business')->get();
    }

    private function mustFind(int $id): Store
    {
        $store = $this->storeRepository->findById($id);
        if (!$store) {
            throw new \Exception('Store not found.');
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
        $businesses = $user->businesses()->with('stores')->get();
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
                ];
            }
        }

        return $result;
    }

    private function getAssignedStores(User $user): array
    {
        $stores = $user->stores()
            ->with('business')
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
            ];
        }

        return $result;
    }
}