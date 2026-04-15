<?php

declare(strict_types=1);

namespace Chuoke\MediaBridge\Drivers;

use Chuoke\MediaBridge\Data\MediaItem;
use Chuoke\MediaBridge\Data\MediaResult;
use Chuoke\MediaBridge\Data\MediaVariant;

/**
 * Wikimedia Commons driver.
 *
 * @see https://www.mediawiki.org/wiki/API:Search
 * @see https://www.mediawiki.org/wiki/API:Allimages
 *
 * All content on Wikimedia Commons is freely licensed; we surface the
 * per-image license from extmetadata when available.
 */
class WikimediaDriver extends AbstractDriver
{
    protected string $baseUri = 'https://commons.wikimedia.org/w/';

    public function source(): string
    {
        return 'wikimedia';
    }

    public function requiresApiKey(): bool
    {
        return false;
    }

    protected function defaultHeaders(): array
    {
        return [
            'User-Agent' => 'chuoke/media-bridge/1.0 (https://github.com/chuoke/media-bridge)',
        ];
    }

    public function search(string $query = '', int $page = 1, int $perPage = 20, array $extras = []): MediaResult
    {
        $isVideo = ($extras['media_type'] ?? 'photo') === 'video';

        if ($query !== '') {
            return $this->searchFiles($query, $page, $perPage, $isVideo);
        }

        return $this->browseLatest($page, $perPage, $isVideo, $extras);
    }

    private function commonImageInfoParams(): array
    {
        return [
            'prop' => 'imageinfo',
            'iiprop' => 'url|user|size|extmetadata',
            'iiurlwidth' => 400,
            'format' => 'json',
        ];
    }

    private function searchFiles(string $query, int $page, int $perPage, bool $isVideo): MediaResult
    {
        $namespace = $isVideo ? 6 : 6;
        $filemimeType = $isVideo ? 'video' : 'image';
        $search = trim('filetype:' . $filemimeType . ' ' . $query);

        $params = array_merge($this->commonImageInfoParams(), [
            'action' => 'query',
            'generator' => 'search',
            'gsrnamespace' => $namespace,
            'gsrsearch' => $search,
            'gsrlimit' => $perPage,
            'gsroffset' => ($page - 1) * $perPage,
        ]);

        $response = $this->httpClient()->get('api.php', ['query' => $params]);
        $this->checkResponse($response, $this->source());

        $data = $this->parseJson($response);
        $pages = $data['query']['pages'] ?? [];
        $total = $data['query']['searchinfo']['totalhits'] ?? count($pages);
        $hasMore = isset($data['continue']);

        $items = array_values(array_filter(
            array_map(fn (array $page) => $this->mapPage($page), $pages)
        ));

        return new MediaResult(
            items: $items,
            total: (int) $total,
            hasMore: $hasMore,
            page: $page,
            perPage: $perPage,
            nextPage: $hasMore ? $page + 1 : null,
        );
    }

    private function browseLatest(int $page, int $perPage, bool $isVideo, array $extras): MediaResult
    {
        if ($isVideo) {
            return $this->searchFiles('', $page, $perPage, true);
        }

        $params = array_merge($this->commonImageInfoParams(), [
            'action' => 'query',
            'generator' => 'allimages',
            'gaisort' => 'timestamp',
            'gaidir' => 'older',
            'gailimit' => $perPage,
        ]);

        if (isset($extras['cursor']) && is_array($extras['cursor'])) {
            $params = array_merge($params, $extras['cursor']);
        }

        $data = $this->fetchBrowsePage($params);
        $pages = $data['query']['pages'] ?? [];
        $hasMore = isset($data['continue']);

        $items = array_values(array_filter(
            array_map(fn (array $page) => $this->mapPage($page), $pages)
        ));

        return new MediaResult(
            items: $items,
            total: count($items),
            hasMore: $hasMore,
            page: $page,
            perPage: $perPage,
            nextPage: $hasMore ? $data['continue'] : null,
        );
    }

    private function fetchBrowsePage(array $params): array
    {
        $response = $this->httpClient()->get('api.php', ['query' => $params]);
        $this->checkResponse($response, $this->source());

        return $this->parseJson($response);
    }

    private function mapPage(array $page): ?MediaItem
    {
        $info = $page['imageinfo'][0] ?? null;
        if (! $info) {
            return null;
        }

        $url = $info['url'] ?? '';
        if (! $url) {
            return null;
        }

        $meta = $info['extmetadata'] ?? [];
        $title = $page['title'] ?? '';
        if (str_starts_with($title, 'File:')) {
            $title = substr($title, 5);
        }
        $title = pathinfo($title, PATHINFO_FILENAME);

        $license = strtolower($meta['LicenseShortName']['value'] ?? 'unknown');
        $license = $this->normalizeLicense($license);

        $artist = strip_tags($meta['Artist']['value'] ?? '');
        if (! $artist) {
            $artist = $info['user'] ?? null;
        }

        $description = strip_tags($meta['ImageDescription']['value'] ?? '');
        $dateRaw = $meta['DateTimeOriginal']['value'] ?? $meta['DateTime']['value'] ?? null;
        $displayDate = $this->parseDate($dateRaw);

        $width = $info['width'] ?? null;
        $height = $info['height'] ?? null;

        $thumbUrl = $info['thumburl'] ?? '';
        if (! $thumbUrl) {
            $thumbUrl = $url;
        }
        $variants = [
            new MediaVariant('thumb', $thumbUrl, $info['thumbwidth'] ?? null, $info['thumbheight'] ?? null),
            new MediaVariant('original', $url, $width, $height),
        ];

        $mediaType = str_contains($url, '.webm') || str_contains($url, '.ogv') || str_contains($url, '.mp4')
            ? 'video'
            : 'photo';

        $descriptionUrl = $info['descriptionurl'] ?? null;
        $pageId = (string) ($page['pageid'] ?? md5($url));

        $categories = $meta['Categories']['value'] ?? '';
        $tags = $categories
            ? array_map('trim', explode('|', $categories))
            : [];

        return new MediaItem(
            source: $this->source(),
            source_id: $pageId,
            media_type: $mediaType,
            url: $url,
            thumb_url: $thumbUrl,
            license: $license,
            tags: $tags,
            title: $title ?: null,
            description: $description ?: null,
            download_url: null,
            author_name: $artist ?: null,
            author_url: $descriptionUrl,
            width: $width ? (int) $width : null,
            height: $height ? (int) $height : null,
            color: null,
            display_date: $displayDate,
            variants: $variants,
        );
    }

    private function normalizeLicense(string $license): string
    {
        if (str_contains($license, 'cc0') || str_contains($license, 'public domain')) {
            return 'cc0';
        }
        if (str_starts_with($license, 'cc')) {
            return $license;
        }

        return 'unknown';
    }

    private function parseDate(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }
        if (preg_match('/^(\d{4})$/', $raw, $m)) {
            return $m[1] . '-01-01';
        }

        return null;
    }
}
