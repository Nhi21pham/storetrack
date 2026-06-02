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
                PermissionEnum::CREATE_SUPPLIER,
                PermissionEnum::UPDATE_SUPPLIER,
                PermissionEnum::DELETE_SUPPLIER,
                PermissionEnum::CREATE_CUSTOMER,
                PermissionEnum::UPDATE_CUSTOMER,
                PermissionEnum::DELETE_CUSTOMER,
                PermissionEnum::CREATE_INVOICE,
                PermissionEnum::UPDATE_INVOICE,
                PermissionEnum::DELETE_INVOICE,
                PermissionEnum::CREATE_BANK,
                PermissionEnum::UPDATE_BANK,
                PermissionEnum::DELETE_BANK,
                PermissionEnum::CREATE_BANK_ACCOUNT,
                PermissionEnum::UPDATE_BANK_ACCOUNT,
                PermissionEnum::DELETE_BANK_ACCOUNT,
                PermissionEnum::CREATE_UNIT,
                PermissionEnum::UPDATE_UNIT,
                PermissionEnum::DELETE_UNIT,
                PermissionEnum::CREATE_PRODUCT,
                PermissionEnum::UPDATE_PRODUCT,
                PermissionEnum::DELETE_PRODUCT,
                PermissionEnum::CREATE_PRODUCT_CATEGORY,
                PermissionEnum::UPDATE_PRODUCT_CATEGORY,
                PermissionEnum::DELETE_PRODUCT_CATEGORY,
                PermissionEnum::CREATE_TAG,
                PermissionEnum::UPDATE_TAG,
                PermissionEnum::DELETE_TAG_VALUE,
                PermissionEnum::DELETE_TAG_KEY,
            ],
            self::ACCOUNTANT => [
                PermissionEnum::UPDATE_STORE,
                PermissionEnum::CREATE_SUPPLIER,
                PermissionEnum::UPDATE_SUPPLIER,
                PermissionEnum::DELETE_SUPPLIER,
                PermissionEnum::CREATE_CUSTOMER,
                PermissionEnum::UPDATE_CUSTOMER,
                PermissionEnum::DELETE_CUSTOMER,
                PermissionEnum::CREATE_INVOICE,
                PermissionEnum::UPDATE_INVOICE,
                PermissionEnum::DELETE_INVOICE,
                PermissionEnum::CREATE_BANK,
                PermissionEnum::UPDATE_BANK,
                PermissionEnum::DELETE_BANK,
                PermissionEnum::CREATE_BANK_ACCOUNT,
                PermissionEnum::UPDATE_BANK_ACCOUNT,
                PermissionEnum::DELETE_BANK_ACCOUNT,
                PermissionEnum::CREATE_UNIT,
                PermissionEnum::UPDATE_UNIT,
                PermissionEnum::DELETE_UNIT,
                PermissionEnum::CREATE_PRODUCT,
                PermissionEnum::UPDATE_PRODUCT,
                PermissionEnum::DELETE_PRODUCT,
                PermissionEnum::CREATE_PRODUCT_CATEGORY,
                PermissionEnum::UPDATE_PRODUCT_CATEGORY,
                PermissionEnum::DELETE_PRODUCT_CATEGORY,
                PermissionEnum::CREATE_TAG,
                PermissionEnum::UPDATE_TAG,
                PermissionEnum::DELETE_TAG_VALUE,
                PermissionEnum::DELETE_TAG_KEY,
            ],
            self::STAFF => [
                PermissionEnum::CREATE_SUPPLIER,
                PermissionEnum::UPDATE_SUPPLIER,
                PermissionEnum::CREATE_CUSTOMER,
                PermissionEnum::UPDATE_CUSTOMER,
                PermissionEnum::CREATE_INVOICE,
                PermissionEnum::UPDATE_INVOICE,
                PermissionEnum::CREATE_BANK,
                PermissionEnum::UPDATE_BANK,
                PermissionEnum::CREATE_BANK_ACCOUNT,
                PermissionEnum::UPDATE_BANK_ACCOUNT,
                PermissionEnum::CREATE_UNIT,
                PermissionEnum::UPDATE_UNIT,
                PermissionEnum::CREATE_PRODUCT,
                PermissionEnum::UPDATE_PRODUCT,
                PermissionEnum::CREATE_PRODUCT_CATEGORY,
                PermissionEnum::UPDATE_PRODUCT_CATEGORY,
                PermissionEnum::CREATE_TAG,
                PermissionEnum::UPDATE_TAG,
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
