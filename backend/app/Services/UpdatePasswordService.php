<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;
use App\Exceptions\AuthException;
use App\Enums\ErrorCode;

class UpdatePasswordService
{
    public function __construct(private UserRepository $userRepository) {}

    public function updatePassword(User $user, string $oldPassword, string $newPassword, string $confirmation): void
    {
        if (!Hash::check($oldPassword, $user->password)) {
            throw new AuthException(ErrorCode::OLD_PASSWORD_INCORRECT, 'Old password is incorrect.');
        }

        if ($newPassword !== $confirmation) {
            throw new AuthException(ErrorCode::PASSWORDS_NOT_MATCH, 'New passwords do not match.');
        }

        $this->userRepository->updatePassword($user->email, $newPassword);
    }
}