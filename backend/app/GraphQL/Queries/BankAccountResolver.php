<?php

namespace App\GraphQL\Queries;

use App\GraphQL\BaseResolver;
use App\Services\BankAccountService;

class BankAccountResolver extends BaseResolver
{
    public function __construct(private BankAccountService $bankAccountService) {}

    public function listForParty($_, array $args)
    {
        return $this->safe(fn() =>
            $this->bankAccountService->listForParty($this->user(), (int) $args['party_id'])
        );
    }

    public function listForBusiness($_, array $args)
    {
        return $this->safe(fn() =>
            $this->bankAccountService->listForBusiness(
                $this->user(),
                (int) $args['business_id'],
                $args['search'] ?? null
            )
        );
    }

    public function findById($_, array $args)
    {
        return $this->safe(fn() =>
            $this->bankAccountService->getById($this->user(), (int) $args['id'])
        );
    }
}
