<?php

declare(strict_types=1);

namespace Chuoke\MediaBridge\Data;

use JsonSerializable;

class MediaItem implements JsonSerializable
{
    /**
     * @param  MediaVariant[]  $variants
     */
    public function __construct(
        public readonly string $source,
        public readonly string $source_id,
        public readonly string $media_type,
        public readonly string $url,
        public readonly string $thumb_url,
        public readonly string $license,
        public readonly array $tags = [],
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly ?string $download_url = null,
        public readonly ?string $author_name = null,
        public readonly ?string $author_url = null,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly ?string $color = null,
        public readonly ?string $display_date = null,
        public readonly array $variants = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'source_id' => $this->source_id,
            'media_type' => $this->media_type,
            'title' => $this->title,
            'description' => $this->description,
            'url' => $this->url,
            'thumb_url' => $this->thumb_url,
            'variants' => array_map(fn (MediaVariant $variant) => $variant->toArray(), $this->variants),
            'download_url' => $this->download_url,
            'author_name' => $this->author_name,
            'author_url' => $this->author_url,
            'license' => $this->license,
            'width' => $this->width,
            'height' => $this->height,
            'color' => $this->color,
            'tags' => $this->tags,
            'display_date' => $this->display_date,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
