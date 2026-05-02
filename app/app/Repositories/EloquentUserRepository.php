<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function all(): Collection
    {
        return User::with('allowedPaths')->get();
    }

    public function create(array $attributes): User
    {
        if (isset($attributes['password']) && $attributes['password'] !== null && $attributes['password'] !== '') {
            $attributes['password'] = Hash::make($attributes['password']);
        }

        return User::create($attributes);
    }

    public function update(User $user, array $attributes): User
    {
        if (array_key_exists('password', $attributes)) {
            if ($attributes['password'] !== null && $attributes['password'] !== '') {
                $attributes['password'] = Hash::make($attributes['password']);
            } else {
                unset($attributes['password']);
            }
        }

        $user->fill($attributes);
        $user->save();

        return $user;
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }
}
