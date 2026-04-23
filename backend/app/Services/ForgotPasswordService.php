<?php

namespace App\Services;

use App\Jobs\SendVerifyMailJob;
use App\Repositories\UserRepository;
use App\Repositories\VerifyRepository;
use App\Exceptions\AuthException;
use App\Enums\ErrorCode;

class ForgotPasswordService
{
    public function __construct(
        private UserRepository $userRepository,
        private VerifyRepository $verifyRepository
    ) {}

    public function sendResetCode(string $email): void
    {
        $email = strtolower(trim($email));

        $user = $this->userRepository->findByEmail($email);

        if (!$user) {
            throw new AuthException(ErrorCode::ACCOUNT_NOT_FOUND, 'No account found with this email.');
        }

        $code = $this->verifyRepository->createRandomCode($email);

        $this->verifyRepository->saveCode('reset_code', $email, $code);

        SendVerifyMailJob::dispatch($email, $code);
    }

    public function resetPassword(string $email, string $code, string $password): void
    {
        $email = strtolower(trim($email));

        $storedCode = $this->verifyRepository->getCode('reset_code', $email);

        if (!$storedCode) {
            throw new AuthException(ErrorCode::CODE_EXPIRED, 'Reset code has expired. Please request a new one.');
        }

        if ($storedCode !== $code) {
            throw new AuthException(ErrorCode::INVALID_CODE, 'Invalid reset code.');
        }

        $user = $this->userRepository->findByEmail($email);

        if (!$user) {
            throw new AuthException(ErrorCode::ACCOUNT_NOT_FOUND, 'No account found with this email.');
        }

        $this->userRepository->updatePassword($email, $password);
        $this->verifyRepository->deleteCode('reset_code', $email);
    }
}