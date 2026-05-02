<?php

namespace App\Services\Video;

use App\Models\User;

class UserAccessService
{
    public function canAccessPath(User $user, ?string $path): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        $allowed = $user->allowedPaths()->pluck('path')->toArray();
        if (empty($allowed)) {
            return false;
        }

        $path = trim((string) $path, '/');

        if ($path === '') {
            return true;
        }

        foreach ($allowed as $allowedPath) {
            $allowedPath = trim((string) $allowedPath, '/');

            if ($allowedPath === '' || $path === $allowedPath || str_starts_with($path, $allowedPath . '/')) {
                return true;
            }

            if (str_starts_with($allowedPath, $path . '/')) {
                return true;
            }
        }

        return false;
    }

    public function pruneRedundantPaths(array $paths): array
    {
        $paths = array_map(static fn ($path) => trim((string) $path, '/'), $paths);
        sort($paths);

        $filtered = [];
        foreach ($paths as $path) {
            $isRedundant = false;
            foreach ($filtered as $alreadyAdded) {
                if ($alreadyAdded === '' || $path === $alreadyAdded || str_starts_with($path, $alreadyAdded . '/')) {
                    $isRedundant = true;
                    break;
                }
            }

            if (!$isRedundant) {
                $filtered[] = $path;
            }
        }

        return $filtered;
    }
}
