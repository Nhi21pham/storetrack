<?php

namespace App\GraphQL;

use GraphQL\Error\UserError;

abstract class BaseResolver
{
    protected function user()
    {
        return auth('sanctum')->user();
    }

    protected function safe(\Closure $fn): mixed
    {
        try {
            return $fn();
        } catch (\Exception $e) {
            throw new UserError($e->getMessage());
        }
    }
}