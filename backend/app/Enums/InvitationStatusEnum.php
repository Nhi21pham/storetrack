<?php

namespace App\Enums;

enum InvitationStatusEnum: string
{
    case Pending   = 'pending';
    case Accepted  = 'accepted';
    case Declined  = 'declined';
    case Cancelled = 'cancelled';
    case Expired   = 'expired';
}
