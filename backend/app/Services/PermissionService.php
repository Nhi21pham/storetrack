<?php

namespace App\Services;

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Exceptions\AuthorizationException;
use App\Models\User;
use App\Repositories\PermissionRepository;

class PermissionService
{
    public function __construct(
        private PermissionRepository $permissionRepository
    ) {}

    public function canOnStore(User $user, PermissionEnum $permission, int $storeId): bool
    {
        if ($this->permissionRepository->isStoreInBusinessOwnedBy($user->id, $storeId)) {
            return true;
        }

        $roleValue = $this->permissionRepository->getUserRoleOnStore($user->id, $storeId);

        if (!$roleValue) {
            return false;
        }

        return RoleEnum::from($roleValue)->has($permission);
    }

    public function canOnBusiness(User $user, PermissionEnum $permission, int $businessId): bool
    {
        return match ($permission) {
            PermissionEnum::UPDATE_BUSINESS,
            PermissionEnum::DELETE_BUSINESS,
            PermissionEnum::CREATE_STORE,
            PermissionEnum::UPDATE_CUSTOMER,
            PermissionEnum::DELETE_CUSTOMER,
            PermissionEnum::UPDATE_SUPPLIER,
            PermissionEnum::DELETE_SUPPLIER,
            PermissionEnum::CREATE_BANK_ACCOUNT,
            PermissionEnum::UPDATE_BANK_ACCOUNT,
            PermissionEnum::DELETE_BANK_ACCOUNT => $this->permissionRepository->isBusinessOwner($user->id, $businessId),
            default => false,
        };
    }

    public function canOnAnyStoreInBusiness(User $user, PermissionEnum $permission, int $businessId): bool
    {
        if ($this->permissionRepository->isBusinessOwner($user->id, $businessId)) {
            return true;
        }
        $storeIds = $this->permissionRepository->getUserStoreIdsInBusiness($user->id, $businessId);
        return $this->canOnAnyStore($user, $permission, $storeIds);
    }

    public function authorizeAnyStoreInBusiness(User $user, PermissionEnum $permission, int $businessId): void
    {
        if (!$this->canOnAnyStoreInBusiness($user, $permission, $businessId)) {
            throw new AuthorizationException(
                "You do not have permission to perform '{$permission->value}' in this business."
            );
        }
    }

    public function authorizeStore(User $user, PermissionEnum $permission, int $storeId): void
    {
        if (!$this->canOnStore($user, $permission, $storeId)) {
            throw new AuthorizationException(
                "You do not have permission to perform '{$permission->value}' on this store."
            );
        }
    }

    public function authorizeBusiness(User $user, PermissionEnum $permission, int $businessId): void
    {
        if (!$this->canOnBusiness($user, $permission, $businessId)) {
            throw new AuthorizationException(
                "You do not have permission to perform '{$permission->value}' on this business."
            );
        }
    }

    /** Guard against cross-business spoofing: row's actual business must match the claimed one. */
    public function assertSameBusiness(int $rowBusinessId, int $claimedBusinessId): void
    {
        if ($rowBusinessId !== $claimedBusinessId) {
            throw new AuthorizationException('You do not have access to this resource.');
        }
    }

    public function canOnAnyStore(User $user, PermissionEnum $permission, array $storeIds): bool
    {
        foreach ($storeIds as $storeId) {
            if ($this->canOnStore($user, $permission, (int) $storeId)) {
                return true;
            }
        }
        return false;
    }

    public function authorizeAnyStore(User $user, PermissionEnum $permission, array $storeIds): void
    {
        if (!$this->canOnAnyStore($user, $permission, $storeIds)) {
            throw new AuthorizationException(
                "You do not have permission to perform '{$permission->value}' on this resource."
            );
        }
    }

    public function getUserRoleOnStore(User $user, int $storeId): ?RoleEnum
    {
        $roleValue = $this->permissionRepository->getUserRoleOnStore($user->id, $storeId);
        return $roleValue ? RoleEnum::from($roleValue) : null;
    }
}