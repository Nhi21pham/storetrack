<?php

namespace App\Enums;

enum AuditAction: string
{
    case CREATED      = 'Created';
    case UPDATED      = 'Updated';
    case DEACTIVATED  = 'Deactivated';
    case REACTIVATED  = 'Reactivated';
    case ASSIGNED     = 'Assigned';
    case ROLE_CHANGED = 'Role_changed';
    case REMOVED      = 'Removed';
    case INVITED      = 'Invited';
    case CANCELLED    = 'Cancelled';
    case ACCEPTED     = 'Accepted';
    case DECLINED     = 'Declined';
}
