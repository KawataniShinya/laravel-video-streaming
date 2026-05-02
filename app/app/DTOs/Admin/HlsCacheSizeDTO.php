<?php

namespace App\DTOs\Admin;

use JsonSerializable;

final class HlsCacheSizeDTO implements JsonSerializable
{
    public function __construct(
        public readonly string $hash,
        public readonly int $sizeBytes,
        public readonly string $sizeFormatted,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'hash' => $this->hash,
            'size_bytes' => $this->sizeBytes,
            'size_formatted' => $this->sizeFormatted,
        ];
    }
}
