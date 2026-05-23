<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\BaseResolver;
use App\Services\BankAccountService;

class BankAccountResolver extends BaseResolver
{
    public function __construct(private BankAccountService $bankAccountService) {}

    public function create($_, array $args)
    {
        return $this->safe(function () use ($args) {
            $partyId = (int) $args['party_id'];
            unset($args['party_id']);
            return $this->bankAccountService->create($this->user(), $partyId, $args);
        });
    }

    public function update($_, array $args)
    {
        return $this->safe(function () use ($args) {
            $id = (int) $args['id'];
            unset($args['id']);
            return $this->bankAccountService->update($this->user(), $id, $args);
        });
    }

    public function delete($_, array $args): bool
    {
        return $this->safe(function () use ($args) {
            $this->bankAccountService->delete($this->user(), (int) $args['id']);
            return true;
        });
    }
}
