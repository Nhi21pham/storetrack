<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;

class AuthorizationException extends AppException
{
    public function __construct(string $message = 'You do not have permission for this action.')
    {
        parent::__construct(ErrorCode::FORBIDDEN, $message, 403);
    }
}