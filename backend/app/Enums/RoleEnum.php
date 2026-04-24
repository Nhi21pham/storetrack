<?php

namespace App\Enums;

enum RoleEnum: string
{
    case OWNER      = 'owner';
    case ACCOUNTANT = 'accountant';
    case STAFF      = 'staff';

    /** @return PermissionEnum[] */
    public function permissions(): array
    {
        return match ($this) {
            self::OWNER => [
                PermissionEnum::UPDATE_STORE,
                PermissionEnum::DEACTIVATE_STORE,
                PermissionEnum::REACTIVATE_STORE,
                PermissionEnum::ASSIGN_STORE_USER,
                PermissionEnum::REMOVE_STORE_USER,
                PermissionEnum::CREATE_INVOICE,
                PermissionEnum::UPDATE_INVOICE,
                PermissionEnum::DELETE_INVOICE,
            ],
            self::ACCOUNTANT => [
                PermissionEnum::UPDATE_STORE,
                PermissionEnum::CREATE_INVOICE,
                PermissionEnum::UPDATE_INVOICE,
                PermissionEnum::DELETE_INVOICE,
            ],
            self::STAFF => [
                PermissionEnum::CREATE_INVOICE,
                PermissionEnum::UPDATE_INVOICE,
            ],
        };
    }

    public function has(PermissionEnum $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    public static function labels(): array
    {
        return [
            self::OWNER->value      => 'Owner',
            self::ACCOUNTANT->value => 'Accountant',
            self::STAFF->value      => 'Staff',
        ];
    }
}
