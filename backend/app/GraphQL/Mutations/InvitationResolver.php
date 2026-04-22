<?php

namespace App\GraphQL\Mutations;

use App\Enums\RoleEnum;
use App\GraphQL\BaseResolver;
use App\Services\InvitationService;

class InvitationResolver extends BaseResolver
{
    public function __construct(private InvitationService $invitationService) {}

    public function invite($_, array $args)
    {
        return $this->safe(fn() =>
            $this->invitationService->sendInvitation(
                $this->user(),
                $args['store_id'],
                $args['email'],
                RoleEnum::from(strtolower($args['role']))
            )
        );
    }

    public function cancel($_, array $args)
    {
        return $this->safe(function () use ($args) {
            $this->invitationService->cancelInvitation($this->user(), $args['invitation_id']);
            return true;
        });
    }

    public function accept($_, array $args)
    {
        return $this->safe(fn() =>
            $this->invitationService->acceptInvitation($this->user(), $args['token'])
        );
    }

    public function decline($_, array $args)
    {
        return $this->safe(function () use ($args) {
            $this->invitationService->declineInvitation($this->user(), $args['token']);
            return true;
        });
    }
}
