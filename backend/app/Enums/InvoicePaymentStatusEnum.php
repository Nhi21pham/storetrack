<?php

namespace App\Enums;

enum InvoicePaymentStatusEnum: string
{
    case UNPAID  = 'unpaid';
    case PARTIAL = 'partial';
    case PAID    = 'paid';

    public static function fromAmounts(float $paidAmount, float $grandTotal): self
    {
        if ($paidAmount <= 0) {
            return self::UNPAID;
        }

        if ($paidAmount >= $grandTotal) {
            return self::PAID;
        }

        return self::PARTIAL;
    }
}
