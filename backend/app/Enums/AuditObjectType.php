<?php

namespace App\Enums;

enum AuditObjectType: string
{
    case BUSINESS   = 'Business';
    case STORE      = 'Store';
    case USER       = 'User';
    case INVITATION = 'Invitation';
}
