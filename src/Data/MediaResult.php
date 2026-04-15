<?php

declare(strict_types=1);

namespace Chuoke\MediaBridge\Data;

use JsonSerializable;

class MediaResult implements JsonSerializable
{
    /**
     * @param  MediaItem[]  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly bool $hasMore,
        public readonly int $page,
        public readonly int $perPage,
        public readonly int|string|array|null $nextPage = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'items' => array_map(fn (MediaItem $item) => $item->toArray(), $this->items),
            'total' => $this->total,
            'has_more' => $this->hasMore,
            'page' => $this->page,
            'per_page' => $this->perPage,
            'next_page' => $this->nextPage,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
