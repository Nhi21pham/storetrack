<?php

namespace App\Services;

use App\Jobs\SendVerifyMailJob;
use App\Repositories\UserRepository;
use App\Repositories\VerifyRepository;
use App\Exceptions\AuthException;
use App\Enums\ErrorCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class ForgotPasswordService
{
    private const RESET_CODE = 'reset_code';
    private const MAX_SEND_ATTEMPTS = 5;
    private const SEND_DECAY_SECONDS = 900;
    private const MAX_CODE_ATTEMPTS = 5;

    public function __construct(
        private UserRepository $userRepository,
        private VerifyRepository $verifyRepository
    ) {}

    public function sendResetCode(string $email): void
    {
        $email = strtolower(trim($email));

        $throttleKey = 'reset-code-send:' . $email;
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_SEND_ATTEMPTS)) {
            throw new AuthException(ErrorCode::RATE_LIMITED, 'Too many requests. Please wait before requesting another code.');
        }
        RateLimiter::hit($throttleKey, self::SEND_DECAY_SECONDS);

        $user = $this->userRepository->findByEmail($email);

        // No-op for unknown emails so the response can't reveal which accounts exist.
        if (!$user) {
            return;
        }

        $code = $this->verifyRepository->createRandomCode($email);

        $this->verifyRepository->saveCode(self::RESET_CODE, $email, $code);
        $this->verifyRepository->clearAttempts(self::RESET_CODE, $email);

        SendVerifyMailJob::dispatch($email, $code);
    }

    public function resetPassword(string $email, string $code, string $password): void
    {
        $email = strtolower(trim($email));

        $storedCode = $this->verifyRepository->getCode(self::RESET_CODE, $email);

        if (!$storedCode) {
            throw new AuthException(ErrorCode::CODE_EXPIRED, 'Reset code has expired. Please request a new one.');
        }

        if ($storedCode !== $code) {
            $attempts = $this->verifyRepository->incrementAttempts(self::RESET_CODE, $email);

            if ($attempts >= self::MAX_CODE_ATTEMPTS) {
                $this->verifyRepository->deleteCode(self::RESET_CODE, $email);
                $this->verifyRepository->clearAttempts(self::RESET_CODE, $email);
                throw new AuthException(ErrorCode::RESET_CODE_LOCKED, 'Too many incorrect attempts. Please request a new code.');
            }

            throw new AuthException(ErrorCode::INVALID_CODE, 'Invalid reset code.');
        }

        $user = $this->userRepository->findByEmail($email);

        // Treat a missing user like a bad code — never reveal whether the account exists.
        if (!$user) {
            throw new AuthException(ErrorCode::INVALID_CODE, 'Invalid reset code.');
        }

        if (Hash::check($password, $user->password)) {
            throw new AuthException(ErrorCode::PASSWORD_SAME_AS_CURRENT, 'New password must be different from your current password.');
        }

        $this->userRepository->updatePassword($email, $password);
        $this->verifyRepository->deleteCode(self::RESET_CODE, $email);
        $this->verifyRepository->clearAttempts(self::RESET_CODE, $email);

        // Log out every session after a password change.
        $user->tokens()->delete();
    }
}
