<?php

namespace App\GraphQL\Mutations;

use App\Services\VerifyService;
use GraphQL\Error\UserError;

class ResendCodeResolver
{
    public function __construct(private VerifyService $verifyService) {}

    public function resendCode(null $root, array $args): array
    {
        try {
            $this->verifyService->resendCode($args['email']);
            return ['message' => 'Verification code resent successfully!'];
        } catch (\Exception $e) {
            throw new UserError($e->getMessage());
        }
    }
}
