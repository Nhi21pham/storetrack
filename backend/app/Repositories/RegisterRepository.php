<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Cache;

class RegisterRepository
{
    public function savePendingUser(string $email, array $data): void
    {
        Cache::put('pending_user:' . $email, $data, now()->addMinutes(10));
    }

    public function getPendingUser(string $email): ?array
    {
        return Cache::get('pending_user:' . $email);
    }

    public function deletePendingUser(string $email): void
    {
        Cache::forget('pending_user:' . $email);
    }
}
