<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class LoginService
{
    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            throw new \Exception('Invalid email address');
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