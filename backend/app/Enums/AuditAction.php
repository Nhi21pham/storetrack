<?php

namespace App\Enums;

enum AuditAction: string
{
    case CREATED      = 'created';
    case UPDATED      = 'updated';
    case DEACTIVATED  = 'deactivated';
    case REACTIVATED  = 'reactivated';
    case ASSIGNED     = 'assigned';
    case ROLE_CHANGED = 'role_changed';
    case REMOVED      = 'removed';
    case INVITED      = 'invited';
    case CANCELLED    = 'cancelled';
    case ACCEPTED     = 'accepted';
    case DECLINED     = 'declined';
}
