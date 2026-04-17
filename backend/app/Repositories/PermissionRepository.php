<?php

namespace App\Repositories;

use App\Models\Business;
use App\Models\Store;

class PermissionRepository
{
    public function getUserRoleOnStore(int $userId, int $storeId): ?string
    {
        return Store::find($storeId)
            ?->users()
            ->where('user_id', $userId)
            ->first()
            ?->pivot
            ?->role;
    }

    public function isBusinessOwner(int $userId, int $businessId): bool
    {
        return Business::where('id', $businessId)
            ->where('owner_id', $userId)
            ->exists();
    }

    public function isStoreInBusinessOwnedBy(int $userId, int $storeId): bool
    {
        return Store::where('id', $storeId)
            ->whereHas('business', fn($q) => $q->where('owner_id', $userId))
            ->exists();
    }
}