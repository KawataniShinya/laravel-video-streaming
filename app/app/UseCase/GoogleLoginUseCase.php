<?php

namespace App\UseCase;

use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;

class GoogleLoginUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    public function findUserByEmail(string $email): ?User
    {
        return $this->userRepository->findByEmail($email);
    }
}
