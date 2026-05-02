<?php

namespace App\Repositories\Interfaces;

use App\Models\User;
use Illuminate\Support\Collection;

interface UserAllowedPathRepositoryInterface
{
    public function allForUser(User $user): Collection;

    public function replaceForUser(User $user, array $paths): void;
}
