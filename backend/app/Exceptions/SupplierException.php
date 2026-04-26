<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;

class SupplierException extends AppException
{
    public function __construct(ErrorCode $errorCode, string $message = '')
    {
        parent::__construct($errorCode, $message, $errorCode->statusCode());
    }
}
