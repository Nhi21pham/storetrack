<?php

namespace App\GraphQL\Mutations;

use App\Services\RegisterService;
use GraphQL\Error\UserError;

class Register
{
    public function __construct(private RegisterService $registerService) {}

    public function __invoke(null $root, array $args): array
    {
        validator($args, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
        ])->validate();

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
