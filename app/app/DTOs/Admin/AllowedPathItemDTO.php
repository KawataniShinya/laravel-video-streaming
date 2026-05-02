<?php

namespace App\DTOs\Admin;

use JsonSerializable;

final class AllowedPathItemDTO implements JsonSerializable
{
    public function __construct(
        public readonly string $type,
        public readonly string $name,
        public readonly string $path,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'path' => $this->path,
        ];
    }
}
