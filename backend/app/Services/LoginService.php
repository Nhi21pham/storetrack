<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;
use App\Repositories\UserRepository;
use App\Exceptions\AuthException;
use App\Enums\ErrorCode;

class LoginService
{
    public function __construct(
        private UserRepository $userRepository
    ) {}

    public function login(string $email, string $password): array
    {
        $user = $this->userRepository->findByEmail($email);

        // Same error for unknown email and wrong password — avoids account enumeration.
        if (!$user || !Hash::check($password, $user->password)) {
            throw new AuthException(ErrorCode::INVALID_CREDENTIALS, 'Incorrect email or password.');
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'message' => 'Login successful!',
            'token' => $token,
            'user' => $user
        ];
    }
}