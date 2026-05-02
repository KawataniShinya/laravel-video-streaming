<?php

namespace App\Repositories\Interfaces;

use App\Models\User;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    public function all(): Collection;

    public function create(array $attributes): User;

    public function update(User $user, array $attributes): User;

    public function delete(User $user): void;

    public function findByEmail(string $email): ?User;
}
