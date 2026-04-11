<?php

namespace App\Services;

use App\Repositories\RegisterRepository;
use Illuminate\Support\Facades\Hash;
use App\Jobs\SendVerifyMailJob;
use App\Repositories\VerifyRepository;
use function Illuminate\Support\microseconds;

class RegisterService
{
    public function __construct(
        private RegisterRepository $registerRepository,
        private VerifyRepository $verifyRepository
    ) {}

    public function register(array $data): array
    {
        $code = $this->verifyRepository->createRandomCode($data['email']);

        $this->registerRepository->savePendingUser($data['email'], [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $this->verifyRepository->saveCode('verification_code', $data['email'], $code);

        SendVerifyMailJob::dispatch($data['email'], $code);

        return ['email' => $data['email']];
    }
}
