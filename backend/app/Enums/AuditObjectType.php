<?php

namespace App\Enums;

enum AuditObjectType: string
{
    case BUSINESS   = 'business';
    case STORE      = 'store';
    case USER       = 'user';
    case INVITATION = 'invitation';
    case SUPPLIER   = 'supplier';
    case CUSTOMER   = 'customer';
    case BANK         = 'bank';
    case BANK_ACCOUNT = 'bank_account';
    case UNIT         = 'unit';
}
