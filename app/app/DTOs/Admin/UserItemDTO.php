<?php

namespace App\DTOs\Admin;

use JsonSerializable;

final class UserItemDTO implements JsonSerializable
{
    /**
     * @param array<int, array{ id:int, path:string }> $allowedPaths
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $role,
        public readonly array $allowedPaths,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'allowed_paths' => $this->allowedPaths,
        ];
    }
}
