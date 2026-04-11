<?php

namespace App\GraphQL\Mutations;

use App\Services\ForgotPasswordService;
use GraphQL\Error\UserError;

class ResetPasswordResolver
{
    public function __construct(private ForgotPasswordService $forgotPasswordService) {}

    public function resetPassword(null $root, array $args): array
    {
        try {
            $this->forgotPasswordService->resetPassword(
                $args['email'],
                $args['code'],
                $args['password']
            );
            return ['message' => 'Password reset successfully!'];
        } catch (\Exception $e) {
            throw new UserError($e->getMessage());
        }
    }
}