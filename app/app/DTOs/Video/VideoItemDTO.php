<?php

namespace App\DTOs\Video;

use JsonSerializable;

final class VideoItemDTO implements JsonSerializable
{
    public function __construct(
        public readonly int $id,
        public readonly string $type,
        public readonly string $name,
        public readonly string $path,
        public readonly string $ext,
        public readonly string $size,
        public readonly bool $isCached,
        public readonly bool $isWatched,
        public readonly bool $isFavorited,
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
            'size' => $this->size,
            'is_cached' => $this->isCached,
            'is_watched' => $this->isWatched,
            'is_favorited' => $this->isFavorited,
        ];
    }
}
