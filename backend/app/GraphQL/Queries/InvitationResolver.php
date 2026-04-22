<?php

namespace App\GraphQL\Queries;

use App\GraphQL\BaseResolver;
use App\Services\InvitationService;

class InvitationResolver extends BaseResolver
{
    public function __construct(private InvitationService $invitationService) {}

    public function pending($_, array $args)
    {
        return $this->safe(fn() =>
            $this->invitationService->getStorePendingInvitations($this->user(), (int) $args['store_id'])
        );
    }

    public function all($_, array $args)
    {
        return $this->safe(fn() =>
            $this->invitationService->getStoreAllInvitations($this->user(), (int) $args['store_id'])
        );
    }

    public function preview($_, array $args)
    {
        return $this->safe(fn() =>
            $this->invitationService->getInvitationPreview($args['token'])
        );
    }
}
