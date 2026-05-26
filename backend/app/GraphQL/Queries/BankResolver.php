<?php

namespace App\GraphQL\Queries;

use App\GraphQL\BaseResolver;
use App\Services\BankService;

class BankResolver extends BaseResolver
{
    public function __construct(private BankService $bankService) {}

    public function all($_, array $args)
    {
        return $this->safe(fn() =>
            $this->bankService->getAll(
                $this->user(),
                (int) $args['business_id'],
                (bool) ($args['include_inactive'] ?? false)
            )
        );
    }

    public function findById($_, array $args)
    {
        return $this->safe(fn() =>
            $this->bankService->getById($this->user(), (int) $args['id'])
        );
    }

    public function search($_, array $args)
    {
        return $this->safe(fn() =>
            $this->bankService->search(
                $this->user(),
                (int) $args['business_id'],
                (string) $args['q'],
                (bool) ($args['include_inactive'] ?? false),
                (int) ($args['limit'] ?? 10)
            )
        );
    }
}
