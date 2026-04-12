<?php

namespace App\GraphQL\Mutations;

use App\Services\VerifyService;
use GraphQL\Error\UserError;
use App\Validators\AuthValidator;

class VerifyCodeResolver
{
    public function __construct(private VerifyService $verifyService) {}

    public function verifyCode(null $root, array $args): array
    {
         validator($args, AuthValidator::verifyCode())->validate();

        try {
            $this->verifyService->verifyCode($args['email'], $args['code']);
            return ['message' => 'Email verified successfully!'];
        } catch (\Exception $e) {
            throw new UserError($e->getMessage());
        }
    }
}
