<?php

namespace App\Services;

use App\Jobs\SendVerifyMailJob;
use App\Repositories\UserRepository;
use App\Repositories\VerifyRepository;

class ForgotPasswordService
{
    public function __construct(
        private UserRepository $userRepository,
        private VerifyRepository $verifyRepository
    ) {}

    public function sendResetCode(string $email): void
    {
        $user = $this->userRepository->findByEmail($email);

        if (!$user) {
            throw new \Exception('No account found with this email.');
        }

        $code = $this->verifyRepository->createRandomCode($email);

        $this->verifyRepository->saveCode('reset_code', $email, $code);

        SendVerifyMailJob::dispatch($email, $code);
    }

    public function resetPassword(string $email, string $code, string $password): void
    {
        $storedCode = $this->verifyRepository->getCode('reset_code', $email);

        if (!$storedCode) {
            throw new \Exception('Reset code has expired. Please request a new one.');
        }

        if ($storedCode !== $code) {
            throw new \Exception('Invalid reset code.');
        }

        $user = $this->userRepository->findByEmail($email);

        if (!$user) {
            throw new \Exception('No account found with this email.');
        }

        $this->userRepository->updatePassword($email, $password);
        $this->verifyRepository->deleteCode('reset_code', $email);
    }
}