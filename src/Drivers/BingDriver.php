<?php

declare(strict_types=1);

namespace Chuoke\MediaBridge\Drivers;

use Chuoke\MediaBridge\Data\MediaItem;
use Chuoke\MediaBridge\Data\MediaResult;
use Chuoke\MediaBridge\Data\MediaVariant;

/**
 * Bing Daily Images driver.
 *
 * @see https://www.bing.com/HPImageArchive.aspx
 *
 * Note: Bing does not support search by query. The query parameter is ignored.
 * Max 8 items per request (Bing API limit).
 * Bing only exposes a shallow daily archive feed. Repeated archive items are deduplicated locally.
 */
class BingDriver extends AbstractDriver
{
    protected string $baseUri = 'https://www.bing.com/';

    public function source(): string
    {
        return 'bing';
    }

    public function requiresApiKey(): bool
    {
        return false;
    }

    public function searchable(): bool
    {
        return false;
    }

    /**
     * @param string $query
     * @param int $page
     * @param int $perPage
     * @param array{
     *   locale?: string, // zh-CN, en-US, ja-JP, en-AU, en-UK, de-DE, en-NZ, en-CA
     * } $extras
     * @return MediaResult
     *
     * Note: Bing only provides a shallow daily feed. The driver fetches the available archive batches
     * and paginates the deduplicated result locally.
     */
    public function search(string $query = '', int $page = 1, int $perPage = 20, array $extras = []): MediaResult
    {
        $page = max($page, 1);
        $perPage = max($perPage, 1);
        $locale = $extras['locale'] ?? '';
        $allItems = array_map(fn (array $image) => $this->mapItem($image), $this->fetchArchiveImages($locale));
        $total = count($allItems);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($allItems, $offset, $perPage);
        $hasMore = ($offset + count($items)) < $total;

        return new MediaResult(
            items: $items,
            total: $total,
            hasMore: $hasMore,
            page: $page,
            perPage: $perPage,
            nextPage: $hasMore ? $page + 1 : null,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchArchiveImages(string $locale): array
    {
        $images = [];

        foreach ([0, 8] as $idx) {
            $response = $this->httpClient()->get('HPImageArchive.aspx', [
                'query' => [
                    'format' => 'js',
                    'idx' => $idx,
                    'n' => 8,
                    'nc' => microtime(true) * 1000,
                    'pid' => 'hp',
                    'mkt' => $locale,
                    'uhd' => 1,
                    'uhdwidth' => 3840,
                    'uhdheight' => 2160,
                ],
            ]);

            $this->checkResponse($response, $this->source());

            if (! $this->mergeArchiveBatch($images, $this->parseJson($response)['images'] ?? [])) {
                break;
            }
        }

        return array_values($images);
    }

    /**
     * @param  array<string, array<string, mixed>>  $images
     * @param  array<int, array<string, mixed>>  $batch
     */
    private function mergeArchiveBatch(array &$images, array $batch): bool
    {
        $batchHasNew = false;

        foreach ($batch as $image) {
            $sourceId = $image['hsh'] ?? md5((string) ($image['url'] ?? ''));

            if (isset($images[$sourceId])) {
                continue;
            }

            $batchHasNew = true;
            $images[$sourceId] = $image;
        }

        return $batchHasNew;
    }

    private function mapItem(array $image): MediaItem
    {
        $baseUri = rtrim($this->baseUri(), '/');

        $imageUrl = $image['url'] ?? '';
        if ($imageUrl && ! str_starts_with($imageUrl, 'http')) {
            $imageUrl = $baseUri . '/' . ltrim($imageUrl, '/');
        }

        $copyrightLink = $image['copyrightlink'] ?? '';
        if ($copyrightLink && ! str_starts_with($copyrightLink, 'http')) {
            $copyrightLink = $baseUri . '/' . ltrim($copyrightLink, '/');
        }

        [$title, $authorName] = $this->parseCopyright($image['copyright'] ?? '');

        $width = $height = null;
        if (preg_match('/_(\d{2,})x(\d{2,})\./', $imageUrl, $m)) {
            $width = (int) $m[1];
            $height = (int) $m[2];
        } elseif (preg_match('/[?&]w=(\d+)/', $imageUrl, $w) && preg_match('/[?&]h=(\d+)/', $imageUrl, $h)) {
            $width = (int) $w[1];
            $height = (int) $h[1];
        }

        $baseImageUrl = explode('&', $imageUrl, 2)[0];
        $thumbUrl = $baseImageUrl . '&pid=hp&w=384&rs=1&c=4';
        $url = $baseImageUrl . '&pid=hp&w=3840&rs=1&c=4';
        $variants = [
            new MediaVariant('thumb', $thumbUrl, 384, $width && $height ? (int) round($height * 384 / $width) : null),
            new MediaVariant('original', $url, $width, $height),
        ];

        $displayDate = null;
        if (! empty($image['startdate']) && strlen($image['startdate']) === 8) {
            $displayDate = substr($image['startdate'], 0, 4) . '-'
                . substr($image['startdate'], 4, 2) . '-'
                . substr($image['startdate'], 6, 2);
        }

        return new MediaItem(
            source: $this->source(),
            source_id: $image['hsh'] ?? md5($imageUrl),
            media_type: 'photo',
            url: $url,
            thumb_url: $thumbUrl,
            license: 'unknown',
            tags: [],
            title: $title ?: ($image['title'] ?? null),
            description: null,
            download_url: null,
            author_name: $authorName ?: null,
            author_url: $copyrightLink ?: null,
            width: $width,
            height: $height,
            color: null,
            display_date: $displayDate,
            variants: $variants,
        );
    }

    private function parseCopyright(string $copyright): array
    {
        if (preg_match('/^(.*?)\s*\(©(.*?)\)\s*$/', $copyright, $m)) {
            return [trim($m[1]), trim($m[2])];
        }

        return [$copyright, ''];
    }
}
