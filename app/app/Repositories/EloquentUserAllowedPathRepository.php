<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Interfaces\UserAllowedPathRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EloquentUserAllowedPathRepository implements UserAllowedPathRepositoryInterface
{
    public function allForUser(User $user): Collection
    {
        return $user->allowedPaths()->get();
    }

    public function replaceForUser(User $user, array $paths): void
    {
        DB::transaction(function () use ($user, $paths) {
            $user->allowedPaths()->delete();

            foreach ($paths as $path) {
                $user->allowedPaths()->create(['path' => $path]);
            }
        });
    }
}
