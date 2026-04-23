<?php

namespace App\Console\Commands;

use App\Enums\InvitationStatusEnum;
use App\Models\Invitation;
use Illuminate\Console\Command;

class ExpireInvitations extends Command
{
    protected $signature = 'invitations:expire';
    protected $description = 'Mark all pending invitations that have passed their expiry date as expired';

    public function handle(): void
    {
        $count = Invitation::where('status', InvitationStatusEnum::Pending->value)
            ->where('expires_at', '<=', now())
            ->update(['status' => InvitationStatusEnum::Expired->value]);

        $this->info("Marked {$count} invitation(s) as expired.");
    }
}
