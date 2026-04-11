<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Repositories\UserRepository;

class LoginService
{
    public function __construct(
        private UserRepository $userRepository
    ) {}

    public function login(string $email, string $password): array
    {
        $user = $this->userRepository->findByEmail($email);

        if (!$user) {
            throw new \Exception('No account found with this email');
        }

        if (!Hash::check($password, $user->password)) {
            throw new \Exception('Invalid password');
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'message' => 'Login successful!',
            'token' => $token,
            'user' => $user
        ];
    }
}