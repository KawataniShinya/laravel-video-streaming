<?php

namespace App\DTOs\Admin;

use JsonSerializable;

final class UserListDTO implements JsonSerializable
{
    /**
     * @param UserItemDTO[] $users
     */
    public function __construct(
        public readonly array $users,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'users' => $this->users,
        ];
    }
}
