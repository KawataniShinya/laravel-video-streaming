<?php

namespace App\DTOs\Admin;

use JsonSerializable;

final class HlsCacheListDTO implements JsonSerializable
{
    /**
     * @param HlsCacheItemDTO[] $caches
     */
    public function __construct(
        public readonly array $caches,
        public readonly string $freeDiskSpace,
        public readonly string $totalDiskSpace,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'caches' => $this->caches,
            'freeDiskSpace' => $this->freeDiskSpace,
            'totalDiskSpace' => $this->totalDiskSpace,
        ];
    }
}
