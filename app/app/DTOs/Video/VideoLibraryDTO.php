<?php

namespace App\DTOs\Video;

use JsonSerializable;

final class VideoLibraryDTO implements JsonSerializable
{
    /**
     * @param VideoItemDTO[] $items
     * @param BreadcrumbDTO[] $breadcrumbs
     */
    public function __construct(
        public readonly array $items,
        public readonly ?string $currentPath,
        public readonly array $breadcrumbs,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'items' => $this->items,
            'currentPath' => $this->currentPath,
            'breadcrumbs' => $this->breadcrumbs,
        ];
    }
}
