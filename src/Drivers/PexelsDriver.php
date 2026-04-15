<?php

declare(strict_types=1);

namespace Chuoke\MediaBridge\Drivers;

use Chuoke\MediaBridge\Data\MediaItem;
use Chuoke\MediaBridge\Data\MediaResult;
use Chuoke\MediaBridge\Data\MediaVariant;

/**
 * Pexels driver.
 *
 * @see https://www.pexels.com/api/documentation/
 */
class PexelsDriver extends AbstractDriver
{
    protected string $baseUri = 'https://api.pexels.com/v1/';

    public function __construct(protected string $apiKey)
    {
    }

    public function source(): string
    {
        return 'pexels';
    }

    protected function defaultHeaders(): array
    {
        return [
            'Authorization' => $this->apiKey,
        ];
    }

    protected function extractErrorMessage(array $error): string
    {
        return $error['error'] ?? '';
    }

    public function search(string $query = '', int $page = 1, int $perPage = 20, array $extras = []): MediaResult
    {
        $isVideo = ($extras['media_type'] ?? 'photo') === 'video';

        if ($isVideo) {
            return $this->fetchVideos($query, $page, $perPage, $extras);
        }

        return $this->fetchPhotos($query, $page, $perPage, $extras);
    }

    private function fetchPhotos(string $query, int $page, int $perPage, array $extras): MediaResult
    {
        $hasFilter = $query !== '';
        $endpoint = $hasFilter ? 'search' : 'curated';

        $params = array_filter([
            'query' => $query ?: null,
            'page' => $page,
            'per_page' => $perPage,
            'orientation' => $extras['orientation'] ?? null,
            'color' => $extras['color'] ?? null,
            'locale' => $extras['locale'] ?? null,
        ]);

        $response = $this->httpClient()->get($endpoint, ['query' => $params]);
        $this->checkResponse($response, $this->source());

        $data = $this->parseJson($response);
        $photos = $data['photos'] ?? [];
        $total = $data['total_results'] ?? count($photos);
        $hasMore = $total > ($data['page'] ?? $page) * ($data['per_page'] ?? $perPage);

        return new MediaResult(
            items: array_map(fn (array $photo) => $this->mapPhoto($photo), $photos),
            total: $total,
            hasMore: $hasMore,
            page: $page,
            perPage: $perPage,
            nextPage: $hasMore ? $page + 1 : null,
        );
    }

    private function fetchVideos(string $query, int $page, int $perPage, array $extras): MediaResult
    {
        $hasFilter = $query !== '';

        $endpoint = $hasFilter ? 'videos/search' : 'videos/popular';

        $params = array_filter([
            'query' => $query ?: null,
            'page' => $page,
            'per_page' => $perPage,
            'orientation' => $extras['orientation'] ?? null,
            'locale' => $extras['locale'] ?? null,
        ]);

        $response = $this->httpClient()->get($endpoint, ['query' => $params]);
        $this->checkResponse($response, $this->source());

        $data = $this->parseJson($response);
        $videos = $data['videos'] ?? [];
        $total = $data['total_results'] ?? count($videos);
        $hasMore = $total > ($data['page'] ?? $page) * ($data['per_page'] ?? $perPage);

        return new MediaResult(
            items: array_map(fn (array $video) => $this->mapVideo($video), $videos),
            total: $total,
            hasMore: $hasMore,
            page: $page,
            perPage: $perPage,
            nextPage: $hasMore ? $page + 1 : null,
        );
    }

    private function mapPhoto(array $photo): MediaItem
    {
        $src = $photo['src'] ?? [];
        $variants = [];

        foreach (['original', 'large2x', 'large', 'medium', 'small', 'portrait', 'landscape', 'tiny'] as $type) {
            if (! empty($src[$type])) {
                $variants[] = new MediaVariant($type, $src[$type]);
            }
        }

        return new MediaItem(
            source: $this->source(),
            source_id: (string) $photo['id'],
            media_type: 'photo',
            url: $src['original'] ?? '',
            thumb_url: $src['medium'] ?? $src['small'] ?? '',
            license: 'pexels',
            tags: [],
            title: $photo['alt'] ?? null,
            description: null,
            download_url: null,
            author_name: $photo['photographer'] ?? null,
            author_url: $photo['photographer_url'] ?? null,
            width: $photo['width'] ?? null,
            height: $photo['height'] ?? null,
            color: $photo['avg_color'] ?? null,
            display_date: null,
            variants: $variants,
        );
    }

    private function mapVideo(array $video): MediaItem
    {
        $videoFiles = $video['video_files'] ?? [];
        usort($videoFiles, fn ($a, $b) => ($b['width'] ?? 0) <=> ($a['width'] ?? 0));

        $bestFile = $videoFiles[0] ?? [];
        $variants = array_values(array_filter(array_map(
            fn (array $file) => empty($file['link']) ? null : new MediaVariant(
                $file['quality'] ?? 'video',
                $file['link'],
                $file['width'] ?? null,
                $file['height'] ?? null,
            ),
            $videoFiles
        )));

        return new MediaItem(
            source: $this->source(),
            source_id: (string) $video['id'],
            media_type: 'video',
            url: $bestFile['link'] ?? '',
            thumb_url: $video['image'] ?? '',
            license: 'pexels',
            tags: [],
            title: null,
            description: null,
            download_url: null,
            author_name: $video['user']['name'] ?? null,
            author_url: $video['url'] ?? null,
            width: $bestFile['width'] ?? null,
            height: $bestFile['height'] ?? null,
            color: null,
            display_date: null,
            variants: $variants,
        );
    }
}
