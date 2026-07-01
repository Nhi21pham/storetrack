<?php

namespace App\Console\Commands;

use App\Repositories\UserRepository;
use Illuminate\Console\Command;

class RestoreDemoAccount extends Command
{
    protected $signature = 'demo:restore';

    protected $description = 'Reset the shared demo account password back to its known value so interviewers are never locked out.';

    public function handle(UserRepository $userRepository): void
    {
        $email = config('demo.email');
        $password = config('demo.password');

        if (!$email || !$password) {
            $this->info('Demo account not configured; nothing to restore.');
            return;
        }

        if (!$userRepository->findByEmail($email)) {
            $this->warn('Demo account '.$email.' not found; nothing to restore.');
            return;
        }

        $userRepository->updatePassword($email, $password);
        $this->info('Demo account password restored.');
    }
}
