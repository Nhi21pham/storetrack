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
        // Business owner always has full access to stores under their business
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
            PermissionEnum::UpdateBusiness,
            PermissionEnum::DeleteBusiness,
            PermissionEnum::CreateStore => $this->permissionRepository->isBusinessOwner($user->id, $businessId),
            default => false,
        };
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

    public function getUserRoleOnStore(User $user, int $storeId): ?RoleEnum
    {
        $roleValue = $this->permissionRepository->getUserRoleOnStore($user->id, $storeId);
        return $roleValue ? RoleEnum::from($roleValue) : null;
    }
}