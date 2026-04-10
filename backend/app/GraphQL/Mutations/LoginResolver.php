<?php

namespace App\GraphQL\Mutations;

use App\Services\LoginService;
use GraphQL\Error\UserError;

class LoginResolver
{
    public function __construct(private LoginService $loginService) {}

    public function login(null $root, array $args): array
    {
        try {
            return $this->loginService->login($args['email'], $args['password']);
        } catch (\Exception $e) {
            throw new UserError($e->getMessage());
        }
    }
}