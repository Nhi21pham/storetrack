<?php

namespace App\GraphQL\Mutations;

use App\Services\LoginService;
use GraphQL\Error\UserError;
use App\Validators\AuthValidator;

class LoginResolver
{
    public function __construct(private LoginService $loginService) {}

    public function login(null $root, array $args): array
    {
        validator($args, AuthValidator::login())->validate();
        try {
            return $this->loginService->login($args['email'], $args['password']);
        } catch (\Exception $e) {
            throw new UserError($e->getMessage());
        }
    }
}