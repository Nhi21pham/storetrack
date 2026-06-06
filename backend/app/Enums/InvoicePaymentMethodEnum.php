<?php

namespace App\Enums;

enum InvoicePaymentMethodEnum: string
{
    case CASH = 'cash';
    case CARD = 'card';
}
