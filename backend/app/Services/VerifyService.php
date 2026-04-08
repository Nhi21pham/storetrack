<?php

namespace App\Services;

use App\Repositories\VerifyRepository;
use App\Repositories\RegisterRepository;
use App\Jobs\SendVerifyMailJob;
use Illuminate\Support\Facades\DB;

class VerifyService
{
    public function __construct(
        private VerifyRepository $verifyRepository,
        private RegisterRepository $registerRepository
    ) {}

    public function verifyCode(string $email, string $code): bool
    {
        $storedCode = $this->verifyRepository->findVerificationCode($email);

        if (!$storedCode) {
            throw new \Exception('Verification code has expired or does not exist.');
        }

        if ($storedCode !== $code) {
            throw new \Exception('Invalid verification code.');
        }

        $pendingUser = $this->verifyRepository->getPendingUser($email);

        if (!$pendingUser) {
            throw new \Exception('Registration session has expired. Please register again.');
        }

        DB::transaction(function () use ($pendingUser, $email) {
            $this->verifyRepository->createUser($pendingUser);
            $this->verifyRepository->cleanup($email);
        });

        return true;
    }

    public function resendCode(string $email): void
    {
        $pendingUser = $this->verifyRepository->getPendingUser($email);

        if (!$pendingUser) {
            throw new \Exception('Registration session has expired. Please register again.');
        }

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->registerRepository->saveVerificationCode($email, $code);

        SendVerifyMailJob::dispatch($email, $code);
    }
}
