<?php

namespace App\GraphQL\Mutations\Tag;

use App\GraphQL\BaseResolver;
use App\Services\Tag\TagValueService;

class TagValueResolver extends BaseResolver
{
    public function __construct(private TagValueService $tagValueService) {}

    public function create($_, array $args)
    {
        return $this->safe(function () use ($args) {
            $tagId = (int) $args['tag_id'];
            unset($args['tag_id']);
            return $this->tagValueService->create($this->user(), $tagId, $args);
        });
    }

    public function addMany($_, array $args)
    {
        return $this->safe(function () use ($args) {
            $tagId = (int) $args['tag_id'];
            return $this->tagValueService->createMany($this->user(), $tagId, $args['values']);
        });
    }

    public function update($_, array $args)
    {
        return $this->safe(function () use ($args) {
            $id = (int) $args['id'];
            unset($args['id']);
            return $this->tagValueService->update($this->user(), $id, $args);
        });
    }

    public function delete($_, array $args): bool
    {
        return $this->safe(function () use ($args) {
            $this->tagValueService->delete($this->user(), (int) $args['id']);
            return true;
        });
    }
}
