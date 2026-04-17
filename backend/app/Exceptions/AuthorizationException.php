<?php

namespace App\Exceptions;

class AuthorizationException extends \Exception
{
    public function __construct(string $message = 'You do not have permission for this action.')
    {
        parent::__construct($message);
    }
}