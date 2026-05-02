<?php

namespace App\DTOs\Admin;

use App\DTOs\Admin\UserItemDTO;
use JsonSerializable;

final class AllowedPathListDTO implements JsonSerializable
{
    /**
     * @param AllowedPathItemDTO[] $items
     */
    public function __construct(
        public readonly UserItemDTO $user,
        public readonly array $items,
        public readonly string $currentPath,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'user' => $this->user,
            'items' => $this->items,
            'currentPath' => $this->currentPath,
        ];
    }
}
