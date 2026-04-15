<?php

declare(strict_types=1);

namespace Chuoke\MediaBridge\Drivers;

use Chuoke\MediaBridge\Data\MediaItem;
use Chuoke\MediaBridge\Data\MediaResult;
use Chuoke\MediaBridge\Data\MediaVariant;

/**
 * Unsplash driver.
 *
 * @see https://unsplash.com/documentation
 *
 * Note: Per Unsplash API guidelines, when a photo is downloaded, you must
 * trigger the download endpoint. The download_url field in MediaItem points
 * to this trigger endpoint — callers must send a GET request to it.
 */
class UnsplashDriver extends AbstractDriver
{
    protected string $baseUri = 'https://api.unsplash.com/';

    public function __construct(protected string $apiKey)
    {
    }

    public function source(): string
    {
        return 'unsplash';
    }

    protected function defaultHeaders(): array
    {
        return [
            'Authorization' => 'Client-ID ' . $this->apiKey,
            'Accept-Version' => 'v1',
        ];
    }

    protected function extractErrorMessage(array $error): string
    {
        return $error['errors'][0] ?? '';
    }

    public function search(string $query = '', int $page = 1, int $perPage = 20, array $extras = []): MediaResult
    {
        if ($this->shouldSearch($query, $extras)) {
            return $this->searchPhotos($query, $page, $perPage, $extras);
        }

        return $this->editorialFeed($page, $perPage, $extras);
    }

    private function shouldSearch(string $query, array $extras): bool
    {
        // When using the search endpoint, a non-empty query is required.
        return strval($query) !== '';
    }

    private function searchPhotos(string $query, int $page, int $perPage, array $extras): MediaResult
    {
        $params = array_filter([
            'query' => $query,
            'page' => $page,
            'per_page' => $perPage,
            'orientation' => $extras['orientation'] ?? null,
            'color' => $extras['color'] ?? null,
            'lang' => isset($extras['locale']) ? $this->toIso6391($extras['locale']) : null,
        ]);

        $response = $this->httpClient()->get('search/photos', ['query' => $params]);
        $this->checkResponse($response, $this->source());

        $data = $this->parseJson($response);
        $photos = $data['results'] ?? [];
        $total = $data['total'] ?? 0;
        $hasMore = $total > ($perPage * $page);

        return new MediaResult(
            items: array_map(fn (array $photo) => $this->mapItem($photo), $photos),
            total: $total,
            hasMore: $hasMore,
            page: $page,
            perPage: $perPage,
            nextPage: $hasMore ? $page + 1 : null,
        );
    }

    private function editorialFeed(int $page, int $perPage, array $extras): MediaResult
    {
        $params = array_filter([
            'page' => $page,
            'per_page' => $perPage,
        ]);

        $response = $this->httpClient()->get('photos', ['query' => $params]);
        $this->checkResponse($response, $this->source());

        $photos = $this->parseJson($response);
        $hasMore = count($photos) >= $perPage;

        return new MediaResult(
            items: array_map(fn (array $photo) => $this->mapItem($photo), $photos),
            total: count($photos),
            hasMore: $hasMore,
            page: $page,
            perPage: $perPage,
            nextPage: $hasMore ? $page + 1 : null,
        );
    }

    private function mapItem(array $photo): MediaItem
    {
        $urls = $photo['urls'] ?? [];
        $links = $photo['links'] ?? [];
        $user = $photo['user'] ?? [];
        $variants = [];

        foreach (['raw', 'full', 'regular', 'small', 'thumb', 'small_s3'] as $type) {
            if (! empty($urls[$type])) {
                $variants[] = new MediaVariant($type, $urls[$type]);
            }
        }

        return new MediaItem(
            source: $this->source(),
            source_id: $photo['id'],
            media_type: 'photo',
            url: $urls['full'] ?? $urls['regular'] ?? '',
            thumb_url: $urls['thumb'] ?? $urls['small'] ?? '',
            license: 'unsplash',
            tags: array_column($photo['tags'] ?? [], 'title'),
            title: $photo['alt_description'] ?? null,
            description: $photo['description'] ?? null,
            download_url: $links['download_location'] ?? null,
            author_name: $user['name'] ?? null,
            author_url: $user['links']['html'] ?? null,
            width: $photo['width'] ?? null,
            height: $photo['height'] ?? null,
            color: $photo['color'] ?? null,
            display_date: isset($photo['created_at'])
                ? substr($photo['created_at'], 0, 10)
                : null,
            variants: $variants,
        );
    }

    private function toIso6391(string $locale): string
    {
        if (! $locale) {
            return '';
        }
        $pos = strpos($locale, '-');

        return $pos !== false ? substr($locale, 0, $pos) : $locale;
    }
}
