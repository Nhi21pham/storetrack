<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class VerifyRepository
{
    public function createRandomCode(string $email): string
    {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Log::info('Saved verification code to Redis: ' . $email . ' code: ' . $code);
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
}
