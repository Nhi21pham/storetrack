<?php

namespace App\Services;

use App\Repositories\RegisterRepository;
use Illuminate\Support\Facades\Hash;
use App\Jobs\SendVerifyMailJob;

use function Illuminate\Support\microseconds;

class RegisterService
{
    public function __construct(
        private RegisterRepository $registerRepository
    ) {}

    public function register(array $data): array
    {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->registerRepository->savePendingUser($data['email'], [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $this->registerRepository->saveVerificationCode($data['email'], $code);

        SendVerifyMailJob::dispatch($data['email'], $code);

        return ['email' => $data['email']];
    }
}
