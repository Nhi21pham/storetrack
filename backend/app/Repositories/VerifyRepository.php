<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class VerifyRepository
{
    public function findVerificationCode(string $email): ?string
    {
        return Cache::get('verification_code:' . $email);
    }

    public function getPendingUser(string $email): ?array
    {
        return Cache::get('pending_user:' . $email);
    }

    public function createUser(array $data): User
    {
        return User::create($data);
    }

    public function cleanup(string $email): void
    {
        Cache::forget('verification_code:' . $email);
        Cache::forget('pending_user:' . $email);
    }
}
