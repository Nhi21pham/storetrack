<?php

namespace App\GraphQL\Queries\Tag;

use App\GraphQL\BaseResolver;
use App\Services\Tag\TagService;

class TagResolver extends BaseResolver
{
    public function __construct(private TagService $tagService) {}

    public function all($_, array $args)
    {
        return $this->safe(fn() =>
            $this->tagService->getAll($this->user(), (int) $args['store_id'])
        );
    }

    public function findById($_, array $args)
    {
        return $this->safe(fn() =>
            $this->tagService->getById($this->user(), (int) $args['id'])
        );
    }

    public function search($_, array $args)
    {
        return $this->safe(fn() =>
            $this->tagService->search(
                $this->user(),
                (int) $args['store_id'],
                (string) $args['q'],
                (int) ($args['limit'] ?? 10)
            )
        );
    }
}
