<?php

namespace App\DTOs\Video;

use JsonSerializable;

final class HistoryListDTO implements JsonSerializable
{
    /**
     * @param HistoryItemDTO[] $items
     */
    public function __construct(
        public readonly array $items,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'items' => $this->items,
        ];
    }
}
