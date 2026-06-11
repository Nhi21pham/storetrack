<?php

namespace App\Enums;

enum InvoiceTypeEnum: string
{
    case PURCHASE = 'purchase';
    case SALE     = 'sale';

    public function codePrefix(): string
    {
        return match ($this) {
            self::PURCHASE => 'PUR',
            self::SALE     => 'SAL',
        };
    }
}
