<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;

class AppException extends \Exception
{
    private ErrorCode $errorCode;
    private int $statusCode;

    public function __construct(ErrorCode $errorCode, string $message = '', int $statusCode = 0)
    {
        $this->errorCode = $errorCode;
        $this->statusCode = $statusCode ?: $errorCode->statusCode();
        parent::__construct($message ?: $errorCode->value);
    }

    public function getErrorCode(): ErrorCode
    {
        return $this->errorCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function toArray(): array
    {
        return [
            'code' => $this->errorCode->value,
            'message' => $this->getMessage(),
        ];
    }
}