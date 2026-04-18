<?php

namespace App\GraphQL\Mutations;

use App\Services\UpdateUserService;
use App\Validators\AuthValidator;
use GraphQL\Error\UserError;

class UserResolver
{
    public function __construct(private UpdateUserService $updateUserService) {}

    public function updateUser(null $root, array $args): mixed
    {
        $user = auth('sanctum')->user();

        if (!$user) {
            throw new UserError('Unauthenticated.');
        }

        validator($args, AuthValidator::updateUser())->validate();

        try {
            return $this->updateUserService->updateUser($user, $args);
        } catch (\Exception $e) {
            throw new UserError($e->getMessage());
        }
    }
}