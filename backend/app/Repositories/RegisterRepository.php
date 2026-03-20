<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Cache;

class RegisterRepository
{
    public function savePendingUser(string $email, array $data): void
    {
        Cache::put('pending_user:' . $email, $data, now()->addMinutes(10));
    }

    public function saveVerificationCode(string $email, string $code): void
    {
        Cache::put('verification_code:' . $email, $code, now()->addMinutes(10));
    }
}
