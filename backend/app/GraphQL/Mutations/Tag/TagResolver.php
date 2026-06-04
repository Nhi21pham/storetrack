<?php

namespace App\GraphQL\Mutations\Tag;

use App\GraphQL\BaseResolver;
use App\Services\Tag\TagService;

class TagResolver extends BaseResolver
{
    public function __construct(private TagService $tagService) {}

    public function createKey($_, array $args)
    {
        return $this->safe(function () use ($args) {
            $storeId = (int) $args['store_id'];
            unset($args['store_id']);
            return $this->tagService->createKey($this->user(), $storeId, $args);
        });
    }

    public function updateKey($_, array $args)
    {
        return $this->safe(function () use ($args) {
            $id = (int) $args['id'];
            unset($args['id']);
            return $this->tagService->updateKey($this->user(), $id, $args);
        });
    }

    public function deleteKey($_, array $args): bool
    {
        return $this->safe(function () use ($args) {
            $this->tagService->deleteKey($this->user(), (int) $args['id']);
            return true;
        });
    }
}
