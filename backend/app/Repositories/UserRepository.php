<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserRepository
{
    public function findByEmail(string $email): ?User
    {
        return User::where('email', strtolower(trim($email)))->first();
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function updatePassword(string $email, string $password): void
    {
        User::where('email', strtolower(trim($email)))->update([
            'password' => Hash::make($password)
        ]);
    }
    public function updateUser(User $user, array $data): User
    {
        $user->update(array_filter([
            'name' => $data['name'] ?? null,
        ]));

        return $user->fresh();
    }
}