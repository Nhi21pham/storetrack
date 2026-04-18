<?php

namespace App\GraphQL;

use App\Exceptions\AppException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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
        } catch (\Throwable $e) {
            // Any domain exception 
            if ($e instanceof AppException) {
                throw new SafeError($e->getMessage(), [
                    'code' => $e->getErrorCode()->value,
                    'statusCode' => $e->getStatusCode(),
                ]);
            }

            // Laravel validation
            if ($e instanceof ValidationException) {
                throw new SafeError($e->getMessage(), [
                    'code' => 'VALIDATION_ERROR',
                    'statusCode' => 422,
                ]);
            }

            // Unknown error — log and hide
            Log::error('Resolver error', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => auth('sanctum')->id(),
            ]);

            throw new SafeError('Something went wrong. Please try again.', [
                'code' => 'SERVER_ERROR',
                'statusCode' => 500,
            ]);
        }
    }
}