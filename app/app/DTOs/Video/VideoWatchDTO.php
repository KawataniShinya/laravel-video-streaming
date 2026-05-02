<?php

namespace App\DTOs\Video;

use JsonSerializable;

final class VideoWatchDTO implements JsonSerializable
{
    /**
     * @param BreadcrumbDTO[] $breadcrumbs
     */
    public function __construct(
        public readonly string $filename,
        public readonly string $path,
        public readonly ?string $hash,
        public readonly int $lastPosition,
        public readonly bool $isFavorited,
        public readonly bool $isCached,
        public readonly array $breadcrumbs,
    ) {
    }

    public function jsonSerialize(): array
    {
        $data = [
            'filename' => $this->filename,
            'path' => $this->path,
            'lastPosition' => $this->lastPosition,
            'isFavorited' => $this->isFavorited,
            'isCached' => $this->isCached,
            'breadcrumbs' => $this->breadcrumbs,
        ];

        if ($this->hash !== null) {
            $data['hash'] = $this->hash;
        }

        return $data;
    }
}
