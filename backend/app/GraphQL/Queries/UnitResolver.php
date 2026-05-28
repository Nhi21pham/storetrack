<?php

namespace App\GraphQL\Queries;

use App\GraphQL\BaseResolver;
use App\Services\UnitService;

class UnitResolver extends BaseResolver
{
    public function __construct(private UnitService $unitService) {}

    public function all($_, array $args)
    {
        return $this->safe(fn() =>
            $this->unitService->getAll(
                $this->user(),
                (int) $args['store_id'],
                (bool) ($args['include_inactive'] ?? false)
            )
        );
    }

    public function findById($_, array $args)
    {
        return $this->safe(fn() =>
            $this->unitService->getById($this->user(), (int) $args['id'])
        );
    }

    public function search($_, array $args)
    {
        return $this->safe(fn() =>
            $this->unitService->search(
                $this->user(),
                (int) $args['store_id'],
                (string) $args['q'],
                (bool) ($args['include_inactive'] ?? false),
                (int) ($args['limit'] ?? 10)
            )
        );
    }
}
