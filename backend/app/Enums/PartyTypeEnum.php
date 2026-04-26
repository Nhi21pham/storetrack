<?php

namespace App\Enums;

enum PartyTypeEnum: string
{
    case SUPPLIER = 'supplier';
    case CUSTOMER = 'customer';
    case USER     = 'user';
    case BUSINESS = 'business';
    case STORE    = 'store';
}
