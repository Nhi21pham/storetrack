<?php

namespace App\GraphQL\Mutations;

use App\Services\RegisterService;
use GraphQL\Error\UserError;
use App\Validators\AuthValidator;

class RegisterResolver
{
    public function __construct(private RegisterService $registerService) {}

    public function register(null $root, array $args): array
    {
         validator($args, AuthValidator::register())->validate();

        try {
            $result = $this->registerService->register($args);
            return [
                'message' => 'Please check your email for the verification code.',
                'email' => $result['email']
            ];
        } catch (\Exception $e) {
            throw new UserError($e->getMessage());
        }
    }
}
