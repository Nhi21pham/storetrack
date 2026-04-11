<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserRepository
{
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function updatePassword(string $email, string $password): void
    {
        User::where('email', $email)->update([
            'password' => Hash::make($password)
        ]);
    }
}