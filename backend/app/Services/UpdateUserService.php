<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;

class UpdateUserService
{
    public function __construct(private UserRepository $userRepository) {}

    public function updateUser(User $user, array $data): User
    {
        return $this->userRepository->updateUser($user, $data);
    }
}