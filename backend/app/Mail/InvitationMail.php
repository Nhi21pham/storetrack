<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $inviterName,
        public string $inviteeEmail,
        public string $storeName,
        public string $role,
        public string $token,
    ) {}

    public function build()
    {
        return $this->subject("{$this->inviterName} invited you to join {$this->storeName}")
            ->view('emails.invitation');
    }
}
