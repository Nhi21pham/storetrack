<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\BaseResolver;
use App\Services\ProductCategoryService;

class ProductCategoryResolver extends BaseResolver
{
    public function __construct(private ProductCategoryService $productCategoryService) {}

    public function create($_, array $args)
    {
        return $this->safe(function () use ($args) {
            $storeId = (int) $args['store_id'];
            unset($args['store_id']);
            return $this->productCategoryService->create($this->user(), $storeId, $args);
        });
    }

    public function update($_, array $args)
    {
        return $this->safe(function () use ($args) {
            $id = (int) $args['id'];
            unset($args['id']);
            return $this->productCategoryService->update($this->user(), $id, $args);
        });
    }

    public function delete($_, array $args): bool
    {
        return $this->safe(function () use ($args) {
            $this->productCategoryService->delete($this->user(), (int) $args['id']);
            return true;
        });
    }
}
