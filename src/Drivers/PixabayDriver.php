<?php

declare(strict_types=1);

namespace Chuoke\MediaBridge\Drivers;

use Chuoke\MediaBridge\Data\MediaItem;
use Chuoke\MediaBridge\Data\MediaResult;
use Chuoke\MediaBridge\Data\MediaVariant;

/**
 * Pixabay driver.
 *
 * @see https://pixabay.com/api/docs/
 *
 * Note: Pixabay uses CC0 license for all content.
 * orientation values: 'horizontal' | 'vertical' (Pixabay) mapped from 'landscape' | 'portrait'.
 */
class PixabayDriver extends AbstractDriver
{
    protected string $baseUri = 'https://pixabay.com/api/';

    public function __construct(protected string $apiKey)
    {
    }

    public function source(): string
    {
        return 'pixabay';
    }

    public function search(string $query = '', int $page = 1, int $perPage = 20, array $extras = []): MediaResult
    {
        $isVideo = ($extras['media_type'] ?? 'photo') === 'video';

        if ($isVideo) {
            return $this->fetchVideos($query, $page, $perPage, $extras);
        }

        return $this->fetchImages($query, $page, $perPage, $extras);
    }

    private function buildParams(string $query, int $page, int $perPage, array $extras): array
    {
        $orientation = $this->mapOrientation($extras['orientation'] ?? '');
        $locale = $extras['locale'] ?? '';
        if ($locale && str_contains($locale, '-')) {
            $locale = substr($locale, 0, strpos($locale, '-'));
        }

        return array_filter([
            'key' => $this->apiKey,
            'q' => $query ?: null,
            'page' => $page,
            'per_page' => $perPage,
            'lang' => $locale ?: null,
            'orientation' => $orientation ?: null,
            'colors' => $extras['color'] ?? null,
            'safesearch' => 'true',
        ]);
    }

    private function mapOrientation(string $orientation): string
    {
        return match ($orientation) {
            'landscape' => 'horizontal',
            'portrait' => 'vertical',
            default => '',
        };
    }

    private function fetchImages(string $query, int $page, int $perPage, array $extras): MediaResult
    {
        $response = $this->httpClient()->get('', ['query' => $this->buildParams($query, $page, $perPage, $extras)]);
        $this->checkResponse($response, $this->source());

        $data = $this->parseJson($response);
        $hits = $data['hits'] ?? [];
        $total = $data['totalHits'] ?? count($hits);
        $hasMore = $total > $page * $perPage;

        return new MediaResult(
            items: array_map(fn (array $hit) => $this->mapImage($hit), $hits),
            total: $total,
            hasMore: $hasMore,
            page: $page,
            perPage: $perPage,
            nextPage: $hasMore ? $page + 1 : null,
        );
    }

    private function fetchVideos(string $query, int $page, int $perPage, array $extras): MediaResult
    {
        $response = $this->httpClient()->get('videos/', ['query' => $this->buildParams($query, $page, $perPage, $extras)]);
        $this->checkResponse($response, $this->source());

        $data = $this->parseJson($response);
        $hits = $data['hits'] ?? [];
        $total = $data['totalHits'] ?? count($hits);
        $hasMore = $total > $page * $perPage;

        return new MediaResult(
            items: array_map(fn (array $hit) => $this->mapVideo($hit), $hits),
            total: $total,
            hasMore: $hasMore,
            page: $page,
            perPage: $perPage,
            nextPage: $hasMore ? $page + 1 : null,
        );
    }

    private function mapImage(array $image): MediaItem
    {
        $variants = [];

        foreach ([
            'thumb' => ['url' => 'previewURL', 'width' => 'previewWidth', 'height' => 'previewHeight'],
            'small' => ['url' => 'webformatURL', 'width' => 'webformatWidth', 'height' => 'webformatHeight'],
            'large' => ['url' => 'largeImageURL'],
            'full_hd' => ['url' => 'fullHDURL'],
            'original' => ['url' => 'imageURL', 'width' => 'imageWidth', 'height' => 'imageHeight'],
        ] as $type => $fields) {
            if (!empty($image[$fields['url']])) {
                $variants[] = new MediaVariant(
                    $type,
                    $image[$fields['url']],
                    isset($fields['width']) ? ($image[$fields['width']] ?? null) : null,
                    isset($fields['height']) ? ($image[$fields['height']] ?? null) : null,
                );
            }
        }

        return new MediaItem(
            source: $this->source(),
            source_id: (string) $image['id'],
            media_type: 'photo',
            url: $image['imageURL'] ?? $image['largeImageURL'] ?? '',
            thumb_url: $image['previewURL'] ?? $image['webformatURL'] ?? '',
            license: 'cc0',
            tags: array_map('trim', explode(',', $image['tags'] ?? '')),
            title: null,
            description: null,
            download_url: null,
            author_name: $image['user'] ?? null,
            author_url: $image['pageURL'] ?? null,
            width: $image['imageWidth'] ?? null,
            height: $image['imageHeight'] ?? null,
            color: null,
            display_date: null,
            variants: $variants,
        );
    }

    private function mapVideo(array $video): MediaItem
    {
        $videos = $video['videos'] ?? [];
        $best = $this->selectBestVideoFile($videos);
        $variants = [];

        foreach ($videos as $type => $file) {
            if (!empty($file['url'])) {
                $variants[] = new MediaVariant(
                    $type,
                    $file['url'],
                    $file['width'] ?? null,
                    $file['height'] ?? null,
                );
            }
        }

        return new MediaItem(
            source: $this->source(),
            source_id: (string) $video['id'],
            media_type: 'video',
            url: $best['url'] ?? '',
            thumb_url: $video['userImageURL'] ?? '',
            license: 'cc0',
            tags: array_map('trim', explode(',', $video['tags'] ?? '')),
            title: null,
            description: null,
            download_url: null,
            author_name: $video['user'] ?? null,
            author_url: $video['pageURL'] ?? null,
            width: $best['width'] ?? null,
            height: $best['height'] ?? null,
            color: null,
            display_date: null,
            variants: $variants,
        );
    }

    private function selectBestVideoFile(array $videos): array
    {
        $priority = ['large', 'medium', 'small', 'tiny'];

        foreach ($priority as $size) {
            if (!empty($videos[$size]['url'])) {
                return $videos[$size];
            }
        }

        return array_values(array_filter($videos, fn ($v) => !empty($v['url'])))[0] ?? [];
    }
}
