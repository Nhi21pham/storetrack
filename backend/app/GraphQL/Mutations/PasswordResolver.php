<?php

namespace App\GraphQL\Mutations;

use App\Services\ForgotPasswordService;
use App\Services\UpdatePasswordService;
use GraphQL\Error\UserError;
use App\Validators\AuthValidator;

class PasswordResolver
{
    public function __construct(
        private ForgotPasswordService $forgotPasswordService,
        private UpdatePasswordService $updatePasswordService
    ) {}

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
    public function updatePassword(null $root, array $args): array
    {
        $user = auth('sanctum')->user();
        
        validator($args, AuthValidator::updatePassword())->validate();

        if (!$user) {
            throw new UserError('Unauthenticated.');
        }
        try {
            $this->updatePasswordService->updatePassword(
                $user,
                $args['old_password'],
                $args['new_password'],
                $args['new_password_confirmation']
            );
            return ['message' => 'Password updated successfully!'];
        } catch (\Exception $e) {
            throw new UserError($e->getMessage());
        }
    }
    public function resetPassword(null $root, array $args): array
    {
         validator($args, AuthValidator::resetPassword())->validate();
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