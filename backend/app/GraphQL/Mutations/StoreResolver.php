<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\BaseResolver;
use App\Enums\RoleEnum;
use App\Services\StoreService;

class StoreResolver extends BaseResolver
{
    public function __construct(private StoreService $storeService) {}

    public function create($_, array $args)
    {
        return $this->safe(fn() =>
            $this->storeService->createStore($this->user(), $args)
        );
    }

    public function update($_, array $args)
    {
        return $this->safe(function () use ($args) {
            $id = (int) $args['id'];
            unset($args['id']);
            return $this->storeService->updateStore($this->user(), $id, $args);
        });
    }

    public function deactivate($_, array $args)
    {
        return $this->safe(fn() =>
            $this->storeService->deactivateStore($this->user(), (int) $args['id'])
        );
    }

    public function reactivate($_, array $args)
    {
        return $this->safe(fn() =>
            $this->storeService->reactivateStore($this->user(), (int) $args['id'])
        );
    }

    public function assignUser($_, array $args)
    {
        return $this->safe(fn() =>
            $this->storeService->assignUser(
                $this->user(),
                (int) $args['store_id'],
                (int) $args['user_id'],
                RoleEnum::from($args['role'])
            )
        );
    }

    public function removeUser($_, array $args)
    {
        return $this->safe(fn() =>
            $this->storeService->removeUser(
                $this->user(),
                (int) $args['store_id'],
                (int) $args['user_id']
            )
        );
    }
}