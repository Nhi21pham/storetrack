<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\BaseResolver;
use App\Services\ForgotPasswordService;
use App\Services\UpdatePasswordService;
use GraphQL\Error\UserError;
use App\Validators\AuthValidator;

class PasswordResolver extends BaseResolver
{
    public function __construct(
        private ForgotPasswordService $forgotPasswordService,
        private UpdatePasswordService $updatePasswordService
    ) {}

    public function forgotPassword(null $root, array $args): array
    {
        return $this->safe(function () use ($args) {
            validator($args, AuthValidator::forgotPassword())->validate();
            $this->forgotPasswordService->sendResetCode($args['email']);

            return ['message' => 'Reset code sent to ' . $args['email'] . '! Please check your email.'];
        });
    }

    public function updatePassword(null $root, array $args): array
    {
        $user = $this->user();

        if (!$user) {
            throw new UserError('Unauthenticated.');
        }

        return $this->safe(function () use ($user, $args) {
            validator($args, AuthValidator::updatePassword())->validate();
            $this->updatePasswordService->updatePassword(
                $user,
                $args['old_password'],
                $args['new_password'],
                $args['new_password_confirmation']
            );

            return ['message' => 'Password updated successfully!'];
        });
    }

    public function resetPassword(null $root, array $args): array
    {
        return $this->safe(function () use ($args) {
            validator($args, AuthValidator::resetPassword())->validate();
            $this->forgotPasswordService->resetPassword(
                $args['email'],
                $args['code'],
                $args['password']
            );

            return ['message' => 'Password reset successfully!'];
        });
    }
}
