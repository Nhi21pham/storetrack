<?php

namespace App\Enums;

enum RoleEnum: string
{
    case Owner = 'owner';
    case Accountant = 'accountant';
    case Staff = 'staff';

    /** @return PermissionEnum[] */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => [
                PermissionEnum::UpdateStore,
                PermissionEnum::DeactivateStore,
                PermissionEnum::ReactivateStore,
                PermissionEnum::AssignStoreUser,
                PermissionEnum::RemoveStoreUser,
                PermissionEnum::CreateInvoice,
                PermissionEnum::UpdateInvoice,
                PermissionEnum::DeleteInvoice,
            ],
            self::Accountant => [
                PermissionEnum::UpdateStore,
                PermissionEnum::CreateInvoice,
                PermissionEnum::UpdateInvoice,
                PermissionEnum::DeleteInvoice,
            ],
            self::Staff => [
                PermissionEnum::CreateInvoice,
                PermissionEnum::UpdateInvoice,
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
            self::Owner->value => 'Owner',
            self::Accountant->value => 'Accountant',
            self::Staff->value => 'Staff',
        ];
    }
}