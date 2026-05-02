<?php

namespace App\DTOs\Favorite;

use JsonSerializable;

final class FavoriteItemDTO implements JsonSerializable
{
    public function __construct(
        public readonly int $id,
        public readonly string $type,
        public readonly string $name,
        public readonly string $path,
        public readonly string $ext,
        public readonly bool $isCached,
        public readonly bool $isWatched,
        public readonly int $lastPosition,
        public readonly bool $isFavorited = true,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'path' => $this->path,
            'ext' => $this->ext,
            'is_cached' => $this->isCached,
            'is_watched' => $this->isWatched,
            'last_position' => $this->lastPosition,
            'is_favorited' => $this->isFavorited,
        ];
    }
}
