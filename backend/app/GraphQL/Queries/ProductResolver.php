<?php

namespace App\GraphQL\Queries;

use App\GraphQL\BaseResolver;
use App\Services\ProductService;

class ProductResolver extends BaseResolver
{
    public function __construct(private ProductService $productService) {}

    public function all($_, array $args)
    {
        return $this->safe(fn() =>
            $this->productService->getAll(
                $this->user(),
                (int) $args['store_id'],
                (bool) ($args['include_inactive'] ?? false)
            )
        );
    }

    public function findById($_, array $args)
    {
        return $this->safe(fn() =>
            $this->productService->getById($this->user(), (int) $args['id'])
        );
    }

    public function search($_, array $args)
    {
        return $this->safe(fn() =>
            $this->productService->search(
                $this->user(),
                (int) $args['store_id'],
                (string) $args['q'],
                (bool) ($args['include_inactive'] ?? false),
                (int) ($args['limit'] ?? 10)
            )
        );
    }
}
