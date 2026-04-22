<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvitationResponseMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $inviteeEmail,
        public string $storeName,
        public bool $accepted,
    ) {}

    public function build()
    {
        $subject = $this->accepted
            ? "{$this->inviteeEmail} accepted your invitation"
            : "{$this->inviteeEmail} declined your invitation";

        return $this->subject($subject)->view('emails.invitation_response');
    }
}
