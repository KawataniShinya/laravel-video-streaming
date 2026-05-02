<?php

namespace App\DTOs\Favorite;

use JsonSerializable;

final class FavoriteListDTO implements JsonSerializable
{
    /**
     * @param FavoriteItemDTO[] $items
     */
    public function __construct(
        public readonly array $items,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'items' => $this->items,
        ];
    }
}
