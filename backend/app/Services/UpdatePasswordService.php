<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;

class UpdatePasswordService
{
    public function __construct(private UserRepository $userRepository) {}

    public function updatePassword(User $user, string $oldPassword, string $newPassword, string $confirmation): void
    {
        if (!Hash::check($oldPassword, $user->password)) {
            throw new \Exception('Old password is incorrect.');
        }

        if ($newPassword !== $confirmation) {
            throw new \Exception('New passwords do not match.');
        }

        $this->userRepository->updatePassword($user->email, $newPassword);
    }
}