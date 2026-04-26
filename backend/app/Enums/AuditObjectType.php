<?php

namespace App\Enums;

enum AuditObjectType: string
{
    case BUSINESS   = 'business';
    case STORE      = 'store';
    case USER       = 'user';
    case INVITATION = 'invitation';
    case SUPPLIER   = 'supplier';
}
