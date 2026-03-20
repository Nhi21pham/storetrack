<?php

namespace App\GraphQL\Mutations;

use App\Services\VerifyService;
use GraphQL\Error\UserError;

class VerifyCode
{
    public function __construct(private VerifyService $verifyService) {}

    public function __invoke(null $root, array $args): array
    {
        try {
            $this->verifyService->verifyCode($args['email'], $args['code']);
            return ['message' => 'Email verified successfully!'];
        } catch (\Exception $e) {
            throw new UserError($e->getMessage());
        }
    }
}
