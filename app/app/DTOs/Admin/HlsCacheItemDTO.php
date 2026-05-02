<?php

namespace App\DTOs\Admin;

use JsonSerializable;

final class HlsCacheItemDTO implements JsonSerializable
{
    public function __construct(
        public readonly string $hash,
        public readonly string $path,
        public readonly ?string $size,
        public readonly int $sizeBytes,
        public readonly string $status,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'hash' => $this->hash,
            'path' => $this->path,
            'size' => $this->size,
            'size_bytes' => $this->sizeBytes,
            'status' => $this->status,
        ];
    }
}
