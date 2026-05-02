<?php

namespace App\DTOs\Video;

use JsonSerializable;

final class BreadcrumbDTO implements JsonSerializable
{
    public function __construct(
        public readonly string $name,
        public readonly string $path,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'path' => $this->path,
        ];
    }
}
