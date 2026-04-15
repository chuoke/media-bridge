<?php

declare(strict_types=1);

namespace Chuoke\MediaBridge\Contracts;

use Chuoke\MediaBridge\Data\MediaResult;

interface MediaProviderInterface
{
    /**
     * Search or browse media from this source.
     *
     * Supported $extras keys:
     *   - orientation: 'landscape'|'portrait'|'squarish'
     *   - color: hex or named color hint (platform-dependent)
     *   - locale: BCP 47 language tag, e.g. 'en', 'zh-CN'
     *   - media_type: 'photo'|'video'
     *   - cursor: next-page token returned by cursor-based sources
     */
    public function search(string $query = '', int $page = 1, int $perPage = 20, array $extras = []): MediaResult;

    /**
     * Return the source identifier, e.g. 'bing', 'unsplash'.
     */
    public function source(): string;

    /**
     * Whether this driver requires an API key to function.
     */
    public function requiresApiKey(): bool;

    /**
     * Whether this driver supports keyword search.
     * Drivers like Bing only provide curated/editorial content and ignore the query parameter.
     */
    public function searchable(): bool;
}
