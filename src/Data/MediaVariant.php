<?php

declare(strict_types=1);

namespace Chuoke\MediaBridge\Data;

use JsonSerializable;

class MediaVariant implements JsonSerializable
{
    public function __construct(
        public readonly string $type,
        public readonly string $url,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
    ) {
    }

    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type,
            'url' => $this->url,
            'width' => $this->width,
            'height' => $this->height,
        ], fn (mixed $value) => $value !== null);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
