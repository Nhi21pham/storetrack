<?php

namespace App\GraphQL\Mutations;

use App\Services\ForgotPasswordService;
use GraphQL\Error\UserError;
use App\Validators\AuthValidator;

class ForgotPasswordResolver
{
    public function __construct(private ForgotPasswordService $forgotPasswordService) {}

    public function forgotPassword(null $root, array $args): array
    {
        validator($args, AuthValidator::forgotPassword())->validate();
        try {
            $this->forgotPasswordService->sendResetCode($args['email']);
            return ['message' => 'Reset code sent! Please check your email.'];
        } catch (\Exception $e) {
            throw new UserError($e->getMessage());
        }
    }
}