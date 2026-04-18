<?php

namespace App\GraphQL\Queries;

use App\Repositories\StoreRepository;
use App\Services\PermissionService;
use App\Services\StoreService;
use App\GraphQL\BaseResolver;

class StoreResolver extends BaseResolver
{
    public function __construct(
        private StoreService $storeService,
        private StoreRepository $storeRepository,
        private PermissionService $permissionService
    ) {}

    public function myStores($_, array $args)
    {
        return $this->storeService->getUserStores($this->user());
    }

    public function getStore($_, array $args)
    {
        return $this->safe(function () use ($args) {
            $user = $this->user();
            $storeId = (int) $args['id'];

            $store = $this->storeRepository->findById($storeId);

            if (!$store) {
                throw new \Exception('Store not found.');
            }

            $role = $this->permissionService->getUserRoleOnStore($user, $storeId);
            $isOwner = $store->business->owner_id === $user->id;

            if (!$role && !$isOwner) {
                throw new \Exception('Store not found.');
            }

            return $store;
        });
    }
    public function accessibleStores($_, array $args)
    {
        return $this->storeService->getAccessibleStores($this->user());
    }
}