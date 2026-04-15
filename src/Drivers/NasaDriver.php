<?php

declare(strict_types=1);

namespace Chuoke\MediaBridge\Drivers;

use Chuoke\MediaBridge\Data\MediaItem;
use Chuoke\MediaBridge\Data\MediaResult;
use Chuoke\MediaBridge\Data\MediaVariant;

/**
 * NASA Image and Video Library driver.
 *
 * @see https://images.nasa.gov/docs/images.nasa.gov_api_docs.pdf
 *
 * No API key required. Uses the public images-api.nasa.gov endpoint.
 * All NASA content is in the public domain.
 */
class NasaDriver extends AbstractDriver
{
    protected string $baseUri = 'https://images-api.nasa.gov/';

    /** @var array<string, string[]> */
    protected array $assetCache = [];

    public function source(): string
    {
        return 'nasa';
    }

    public function requiresApiKey(): bool
    {
        return false;
    }

    public function search(string $query = '', int $page = 1, int $perPage = 20, array $extras = []): MediaResult
    {
        $isVideo = ($extras['media_type'] ?? 'photo') === 'video';
        $mediaType = $isVideo ? 'video' : 'image';

        $params = array_filter([
            'q' => $query ?: 'nasa',
            'page' => $page,
            'page_size' => $perPage,
            'media_type' => $mediaType,
            'year_start' => $extras['year_start'] ?? null,
            'year_end' => $extras['year_end'] ?? null,
        ]);

        $response = $this->httpClient()->get('search', ['query' => $params]);
        $this->checkResponse($response, $this->source());

        $data = $this->parseJson($response);
        $collection = $data['collection'] ?? [];
        $items_raw = $collection['items'] ?? [];
        $total = $collection['metadata']['total_hits'] ?? count($items_raw);

        $items = array_values(array_filter(
            array_map(fn (array $item) => $this->mapItem($item, $isVideo), $items_raw)
        ));
        $hasMore = $this->hasNextPage($collection['links'] ?? []);

        return new MediaResult(
            items: $items,
            total: (int) $total,
            hasMore: $hasMore,
            page: $page,
            perPage: $perPage,
            nextPage: $hasMore ? $page + 1 : null,
        );
    }

    private function mapItem(array $item, bool $isVideo): ?MediaItem
    {
        $data = $item['data'][0] ?? null;
        $links = $item['links'] ?? [];

        if (!$data) {
            return null;
        }

        $nasaId = $data['nasa_id'] ?? null;
        if (!$nasaId) {
            return null;
        }

        $thumbUrl = '';
        $url = '';
        foreach ($links as $link) {
            $rel = $link['rel'] ?? 'preview';
            $href = $link['href'] ?? '';
            if ($rel === 'preview' && !$thumbUrl) {
                $thumbUrl = $href;
            }
            if ($rel === 'captions') {
                continue;
            }
            if (!$url && $href) {
                $url = $href;
            }
        }

        if (!$url) {
            $url = $thumbUrl;
        }

        $dateRaw = $data['date_created'] ?? null;
        $displayDate = $dateRaw ? substr($dateRaw, 0, 10) : null;

        $keywords = $data['keywords'] ?? [];
        $assets = $this->assetUrls($item['href'] ?? null, $nasaId);
        $url = $this->resolvePrimaryAssetUrl($assets, $isVideo) ?: $url;

        return new MediaItem(
            source: $this->source(),
            source_id: $nasaId,
            media_type: $isVideo ? 'video' : 'photo',
            url: $url,
            thumb_url: $thumbUrl ?: $url,
            license: 'cc0',
            tags: is_array($keywords) ? $keywords : [],
            title: $data['title'] ?? null,
            description: $data['description'] ?? null,
            download_url: null,
            author_name: $data['photographer'] ?? $data['center'] ?? null,
            author_url: 'https://images.nasa.gov/details/' . urlencode($nasaId),
            width: null,
            height: null,
            color: null,
            display_date: $displayDate,
            variants: $this->assetVariants($assets),
        );
    }

    private function hasNextPage(array $links): bool
    {
        foreach ($links as $link) {
            if (($link['rel'] ?? null) === 'next') {
                return true;
            }
        }

        return false;
    }

    private function resolvePrimaryAssetUrl(array $assets, bool $isVideo): ?string
    {
        if ($isVideo) {
            return $this->selectAsset($assets, [
                '~orig.mp4',
                '~large.mp4',
                '~medium.mp4',
                '~mobile.mp4',
                '~preview.mp4',
                '~small.mp4',
            ], ['mp4', 'mov', 'm4v']);
        }

        return $this->selectAsset($assets, [
            '~orig.jpg',
            '~orig.jpeg',
            '~orig.png',
            '~large.jpg',
            '~large.jpeg',
            '~large.png',
            '~medium.jpg',
            '~medium.jpeg',
            '~medium.png',
            '~small.jpg',
            '~small.jpeg',
            '~small.png',
        ], ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    }

    private function assetUrls(?string $href, string $nasaId): array
    {
        if (isset($this->assetCache[$nasaId])) {
            return $this->assetCache[$nasaId];
        }

        $response = $href
            ? $this->httpClient()->get($href)
            : $this->httpClient()->get('asset/' . rawurlencode($nasaId));

        $this->checkResponse($response, $this->source());

        $data = $this->parseJson($response);
        $assets = [];

        if (array_is_list($data)) {
            $assets = array_map(
                fn (mixed $asset) => is_string($asset) ? $this->normalizeAssetUrl($asset) : '',
                $data
            );
        } else {
            $assets = array_map(
                fn (array $asset) => $this->normalizeAssetUrl($asset['href'] ?? ''),
                $data['collection']['items'] ?? []
            );
        }

        return $this->assetCache[$nasaId] = array_values(array_filter($assets));
    }

    private function selectAsset(array $assets, array $prioritySuffixes, array $allowedExtensions): ?string
    {
        foreach ($prioritySuffixes as $suffix) {
            foreach ($assets as $asset) {
                if (str_ends_with(strtolower($asset), $suffix)) {
                    return $asset;
                }
            }
        }

        $pattern = '/\.(' . implode('|', array_map('preg_quote', $allowedExtensions)) . ')$/i';

        foreach ($assets as $asset) {
            if (preg_match($pattern, $asset)) {
                return $asset;
            }
        }

        return null;
    }

    private function assetVariants(array $assets): array
    {
        return array_values(array_filter(array_map(
            fn (string $asset) => $this->assetVariant($asset),
            $assets
        )));
    }

    private function assetVariant(string $asset): ?MediaVariant
    {
        if (!preg_match('/~([a-z0-9_]+)\.[a-z0-9]+$/i', $asset, $m)) {
            return null;
        }

        return new MediaVariant(strtolower($m[1]), $asset);
    }

    private function normalizeAssetUrl(string $url): string
    {
        if (str_starts_with($url, 'http://images-assets.nasa.gov/')) {
            return 'https://' . substr($url, strlen('http://'));
        }

        return $url;
    }
}
