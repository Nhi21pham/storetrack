<?php

namespace App\GraphQL\Types;

use App\Models\Store;
use App\Services\PermissionService;

class StoreMyRoleResolver
{
    public function __construct(private PermissionService $permissionService) {}

    public function __invoke(Store $store): ?string
    {
        $user = auth('sanctum')->user();
        if (!$user) return null;

        return $this->permissionService->getUserRoleOnStore($user, $store->id)?->value;
    }
}