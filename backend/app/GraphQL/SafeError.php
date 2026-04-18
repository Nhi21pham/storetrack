<?php

namespace App\GraphQL;

use GraphQL\Error\ClientAware;

class SafeError extends \Exception implements ClientAware
{
    private array $extensions;

    public function __construct(string $message, array $extensions = [])
    {
        $this->extensions = $extensions;
        parent::__construct($message);
    }

    public function isClientSafe(): bool
    {
        return true;
    }

    public function getExtensions(): array
    {
        return $this->extensions;
    }
}