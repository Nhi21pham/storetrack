<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\DB;

class TransactionRunner
{
    // Re-run on deadlock / lock-wait timeout; domain exceptions still propagate at once.
    private const DEADLOCK_ATTEMPTS = 3;

    public static function run(Closure $callback, int $attempts = self::DEADLOCK_ATTEMPTS): mixed
    {
        return DB::transaction($callback, $attempts);
    }
}
