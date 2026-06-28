<?php

namespace App\GraphQL;

use GraphQL\Error\ClientAware;
use GraphQL\Error\ProvidesExtensions;

class SafeError extends \Exception implements ClientAware, ProvidesExtensions
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