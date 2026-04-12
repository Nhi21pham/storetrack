<?php

namespace App\GraphQL\Mutations;

use App\Services\UpdatePasswordService;
use GraphQL\Error\UserError;
use App\Validators\AuthValidator;


class UpdatePasswordResolver
{
    public function __construct(private UpdatePasswordService $updatePasswordService) {}

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
}