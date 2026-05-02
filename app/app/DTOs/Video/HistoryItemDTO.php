<?php

namespace App\DTOs\Video;

use JsonSerializable;

final class HistoryItemDTO implements JsonSerializable
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $path,
        public readonly int $lastPosition,
        public readonly string $updatedAt,
        public readonly string $updatedAtHuman,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'path' => $this->path,
            'last_position' => $this->lastPosition,
            'updated_at' => $this->updatedAt,
            'updated_at_human' => $this->updatedAtHuman,
        ];
    }
}
