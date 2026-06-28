<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\BaseResolver;
use App\Services\LoginService;
use App\Validators\AuthValidator;

class LoginResolver extends BaseResolver
{
    public function __construct(private LoginService $loginService) {}

    public function login(null $root, array $args): array
    {
        return $this->safe(function () use ($args) {
            validator($args, AuthValidator::login())->validate();

            return $this->loginService->login($args['email'], $args['password']);
        });
    }
}