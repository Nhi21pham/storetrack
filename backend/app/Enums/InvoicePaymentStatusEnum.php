<?php

namespace App\Enums;

enum InvoicePaymentStatusEnum: string
{
    case UNPAID = 'unpaid';
    case PAID   = 'paid';
}
