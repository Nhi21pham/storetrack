<?php

namespace App\Enums;

enum ImportTypeEnum: string
{
    case UNITS         = 'units';
    case TAGS          = 'tags';
    case BANKS         = 'banks';
    case BANK_ACCOUNTS = 'bank_accounts';
    case CUSTOMERS     = 'customers';
    case SUPPLIERS     = 'suppliers';
    case PRODUCTS      = 'products';
}
