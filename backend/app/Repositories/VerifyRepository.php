<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Cache;

class VerifyRepository
{
    public function createRandomCode(string $email): string
    {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        return $code;
    }

    public function saveCode(string $prefix, string $email, string $code): void
    {
        Cache::put($prefix . ':' . $email, $code, now()->addMinutes(10));
    }

    public function getCode(string $prefix, string $email): ?string
    {
        return Cache::get($prefix . ':' . $email);
    }

    public function deleteCode(string $prefix, string $email): void
    {
        Cache::forget($prefix . ':' . $email);
    }

    public function incrementAttempts(string $prefix, string $email): int
    {
        $key = $prefix . ':attempts:' . $email;
        $attempts = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $attempts, now()->addMinutes(10));

        return $attempts;
    }

    public function clearAttempts(string $prefix, string $email): void
    {
        Cache::forget($prefix . ':attempts:' . $email);
    }
}
