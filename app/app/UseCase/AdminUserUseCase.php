<?php

namespace App\UseCase;

use App\DTOs\Admin\AllowedPathItemDTO;
use App\DTOs\Admin\AllowedPathListDTO;
use App\DTOs\Admin\UserItemDTO;
use App\DTOs\Admin\UserListDTO;
use App\Models\User;
use App\Repositories\Interfaces\UserAllowedPathRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Services\Video\UserAccessService;
use App\Services\Video\VideoPathService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

class AdminUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserAllowedPathRepositoryInterface $allowedPathRepository,
        private readonly UserAccessService $accessService,
        private readonly VideoPathService $pathService,
    ) {
    }

    public function list(User $user): UserListDTO
    {
        $this->authorizeAdmin($user);

        $users = $this->userRepository->all()->map(function (User $user) {
            return new UserItemDTO(
                id: $user->id,
                name: $user->name,
                email: $user->email,
                role: $user->role,
                allowedPaths: $user->allowedPaths->map(fn ($allowed) => [
                    'id' => $allowed->id,
                    'path' => $allowed->path,
                ])->all(),
            );
        })->all();

        return new UserListDTO($users);
    }

    public function create(User $actor, array $data): void
    {
        $this->authorizeAdmin($actor);

        $this->userRepository->create($data);
    }

    public function update(User $actor, User $target, array $data): void
    {
        $this->authorizeAdmin($actor);
        $this->userRepository->update($target, $data);
    }

    public function delete(User $actor, User $target): void
    {
        $this->authorizeAdmin($actor);
        if ($target->id === $actor->id) {
            abort(422, 'You cannot delete yourself.');
        }

        $this->userRepository->delete($target);
    }

    public function editAllowedPaths(User $actor, User $target, ?string $subpath = ''): AllowedPathListDTO
    {
        $this->authorizeAdmin($actor);
        $subpath = $subpath ? rawurldecode($subpath) : '';

        $fullPath = $this->pathService->resolvePath($subpath);
        if (!$fullPath || !File::isDirectory($fullPath)) {
            $subpath = '';
            $fullPath = config('video.root', '/videos');
        }

        $items = [];
        foreach (File::directories($fullPath) as $dir) {
            $relativePath = ($subpath ? $subpath . '/' : '') . basename($dir);
            $items[] = new AllowedPathItemDTO('folder', basename($dir), $relativePath);
        }

        foreach (File::files($fullPath) as $file) {
            $relativePath = ($subpath ? $subpath . '/' : '') . $file->getFilename();
            $items[] = new AllowedPathItemDTO('file', $file->getFilename(), $relativePath);
        }

        return new AllowedPathListDTO(
            user: new UserItemDTO(
                id: $target->id,
                name: $target->name,
                email: $target->email,
                role: $target->role,
                allowedPaths: $target->load('allowedPaths')->allowedPaths->map(fn ($allowed) => [
                    'id' => $allowed->id,
                    'path' => $allowed->path,
                ])->all(),
            ),
            items: $items,
            currentPath: $subpath,
        );
    }

    public function updateAllowedPaths(User $actor, User $target, array $paths): void
    {
        $this->authorizeAdmin($actor);

        $filteredPaths = $this->accessService->pruneRedundantPaths($paths);
        $this->allowedPathRepository->replaceForUser($target, $filteredPaths);
    }

    private function authorizeAdmin(User $user): void
    {
        if ($user->role !== 'admin') {
            abort(403, 'Unauthorized action.');
        }
    }
}
