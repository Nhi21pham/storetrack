<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationCodeMail;

class SendVerifyMailJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public $email, public $code) {}

    public function handle()
    {
        Mail::to($this->email)->send(new VerificationCodeMail($this->code));
    }
}
