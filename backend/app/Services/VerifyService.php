<?php

namespace App\Services;

use App\Repositories\VerifyRepository;
use App\Repositories\RegisterRepository;
use App\Jobs\SendVerifyMailJob;
use Illuminate\Support\Facades\DB;
use App\Enums\ErrorCode;
use App\Enums\PartyTypeEnum;
use App\Exceptions\AuthException;
use App\Repositories\PartyRepository;
use App\Repositories\UserRepository;

class VerifyService
{
    public function __construct(
        private VerifyRepository $verifyRepository,
        private RegisterRepository $registerRepository,
        private UserRepository $userRepository,
        private PartyRepository $partyRepository
    ) {}

    public function verifyCode(string $email, string $code): bool
    {
        $email = strtolower(trim($email));

        $storedCode = $this->verifyRepository->getCode('verification_code', $email);

        if (!$storedCode) {
            throw new AuthException(ErrorCode::CODE_EXPIRED, 'Verification code has expired or does not exist.');
        }

        if ($storedCode !== $code) {
            throw new AuthException(ErrorCode::INVALID_CODE, 'Invalid verification code.');
        }

        $pendingUser = $this->registerRepository->getPendingUser($email);

        if (!$pendingUser) {
            throw new AuthException(ErrorCode::REGISTRATION_EXPIRED, 'Registration session has expired. Please register again.');
        }

        DB::transaction(function () use ($pendingUser) {
            $party = $this->partyRepository->create(PartyTypeEnum::USER);
            $this->userRepository->create(array_merge($pendingUser, ['party_id' => $party->id]));
        });

        $this->verifyRepository->deleteCode('verification_code', $email);
        $this->registerRepository->deletePendingUser($email);

        return true;
    }

    public function resendCode(string $email): void
    {
        $email = strtolower(trim($email));

        $pendingUser = $this->registerRepository->getPendingUser($email);

        if (!$pendingUser) {
            throw new AuthException(ErrorCode::REGISTRATION_EXPIRED, 'Registration session has expired. Please register again.');
        }

        $code = $this->verifyRepository->createRandomCode($email);

        $this->verifyRepository->saveCode('verification_code', $email, $code);

        SendVerifyMailJob::dispatch($email, $code);
    }
}