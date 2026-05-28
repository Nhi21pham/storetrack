<?php

namespace App\GraphQL\Queries;

use App\GraphQL\BaseResolver;
use App\Services\ProductCategoryService;

class ProductCategoryResolver extends BaseResolver
{
    public function __construct(private ProductCategoryService $productCategoryService) {}

    public function all($_, array $args)
    {
        return $this->safe(fn() =>
            $this->productCategoryService->getAll(
                $this->user(),
                (int) $args['store_id'],
                (bool) ($args['include_inactive'] ?? false)
            )
        );
    }

    public function findById($_, array $args)
    {
        return $this->safe(fn() =>
            $this->productCategoryService->getById($this->user(), (int) $args['id'])
        );
    }

    public function search($_, array $args)
    {
        return $this->safe(fn() =>
            $this->productCategoryService->search(
                $this->user(),
                (int) $args['store_id'],
                (string) $args['q'],
                (bool) ($args['include_inactive'] ?? false),
                (int) ($args['limit'] ?? 10)
            )
        );
    }
}
