<?php

namespace App\Services;

use App\Repositories\VerifyRepository;
use App\Repositories\RegisterRepository;
use App\Jobs\SendVerifyMailJob;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Repositories\UserRepository;

class VerifyService
{
    public function __construct(
        private VerifyRepository $verifyRepository,
        private RegisterRepository $registerRepository,
        private UserRepository $userRepository
    ) {}

    public function verifyCode(string $email, string $code): bool
    {
        $storedCode = $this->verifyRepository->getCode('verification_code', $email);

        if (!$storedCode) {
            throw new \Exception('Verification code has expired or does not exist.');
        }

        if ($storedCode !== $code) {
            throw new \Exception('Invalid verification code.');
        }

        $pendingUser = $this->registerRepository->getPendingUser($email);

        if (!$pendingUser) {
            throw new \Exception('Registration session has expired. Please register again.');
        }

        DB::transaction(function () use ($pendingUser, $email) {
            $this->userRepository->create($pendingUser);
            $this->verifyRepository->deleteCode('verification_code', $email);
            $this->registerRepository->deletePendingUser($email);
        });

        return true;
    }

    public function resendCode(string $email): void
    {
        $pendingUser = $this->registerRepository->getPendingUser($email);

        if (!$pendingUser) {
            throw new \Exception('Registration session has expired. Please register again.');
        }

        $code = $this->verifyRepository->createRandomCode($email);

        $this->verifyRepository->saveCode('verification_code', $email, $code);

        SendVerifyMailJob::dispatch($email, $code);
    }
}
