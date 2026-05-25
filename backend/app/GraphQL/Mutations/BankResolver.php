<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\BaseResolver;
use App\Services\BankService;

class BankResolver extends BaseResolver
{
    public function __construct(private BankService $bankService) {}

    public function create($_, array $args)
    {
        return $this->safe(function () use ($args) {
            $businessId = (int) $args['business_id'];
            unset($args['business_id']);
            return $this->bankService->create($this->user(), $businessId, $args);
        });
    }

    public function update($_, array $args)
    {
        return $this->safe(function () use ($args) {
            $id = (int) $args['id'];
            unset($args['id']);
            return $this->bankService->update($this->user(), $id, $args);
        });
    }

    public function delete($_, array $args): bool
    {
        return $this->safe(function () use ($args) {
            $this->bankService->delete($this->user(), (int) $args['id']);
            return true;
        });
    }
}
