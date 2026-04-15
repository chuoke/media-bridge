# chuoke/media-bridge

[![Latest Version on Packagist](https://img.shields.io/packagist/v/chuoke/media-bridge.svg?style=flat-square)](https://packagist.org/packages/chuoke/media-bridge)
[![Tests](https://github.com/chuoke/media-bridge/actions/workflows/run-tests.yml/badge.svg?branch=main)](https://github.com/chuoke/media-bridge/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/chuoke/media-bridge.svg?style=flat-square)](https://packagist.org/packages/chuoke/media-bridge)

A unified bridge for multiple media sources — search photos and videos from Unsplash, Pexels, Pixabay, Bing, Wikimedia Commons, and NASA through a single consistent interface.

This package was renamed from `chuoke/unify-gallery` to `chuoke/media-bridge` in v2.

## Supported Sources

| Driver     | Search | Browse | API Key | Media Types  |
|------------|--------|--------|---------|--------------|
| Bing       | ✗      | ✓      | No      | Photo        |
| Unsplash   | ✓      | ✓      | Yes     | Photo        |
| Pexels     | ✓      | ✓      | Yes     | Photo, Video |
| Pixabay    | ✓      | ✓      | Yes     | Photo, Video |
| Wikimedia  | ✓      | ✓      | No      | Photo, Video |
| NASA       | ✓      | ✓      | No      | Photo, Video |

## Installation

```bash
composer require chuoke/media-bridge
```

For Laravel integration, also install:

```bash
composer require illuminate/support
```

## Usage

### Standalone

```php
use Chuoke\MediaBridge\MediaManager;

$manager = new MediaManager([
    'default' => 'unsplash',
    'drivers' => [
        'unsplash' => ['api_key' => 'your-unsplash-key'],
        'pexels'   => ['api_key' => 'your-pexels-key'],
        'pixabay'  => ['api_key' => 'your-pixabay-key'],
    ],
]);

// Search photos
$result = $manager->driver('pexels')->search('mountain', page: 1, perPage: 20);

// Browse without query (Bing, Wikimedia; NASA falls back to a generic feed)
$result = $manager->driver('bing')->search();

// Use default driver
$result = $manager->search('ocean');

foreach ($result->items as $item) {
    echo $item->source;       // 'pexels'
    echo $item->url;          // full image URL
    echo $item->thumb_url;    // thumbnail URL
    print_r($item->variants); // available size / asset variants
    echo $item->author_name;  // photographer name
    echo $item->license;      // 'pexels', 'cc0', etc.
}
```

### Extra Parameters

```php
$result = $manager->driver('pixabay')->search('forest', extras: [
    'orientation' => 'landscape',   // 'landscape' | 'portrait' | 'squarish'
    'color'       => 'green',
    'media_type'  => 'video',       // 'photo' | 'video'
    'locale'      => 'zh-CN',
]);
```

### Media Variants

```php
foreach ($item->variants as $variant) {
    echo $variant->type;   // 'thumb', 'small', 'large', 'original', etc.
    echo $variant->url;
    echo $variant->width;
    echo $variant->height;
}
```

### Pagination

```php
$result = $manager->driver('wikimedia')->search('', perPage: 20);

if ($result->hasMore) {
    $next = $manager->driver('wikimedia')->search('', perPage: 20, extras: [
        'cursor' => $result->nextPage,
    ]);
}
```

Most drivers return the next numeric page in `nextPage`. Cursor-based sources such as Wikimedia may return an opaque token array; pass it back as `extras['cursor']`.

Bing only exposes a shallow daily archive feed. The driver fetches the available archive batches internally, deduplicates repeated items by `source_id`, and then paginates the merged result locally.

`MediaResult` fields:

| Field       | Type                         | Description                          |
|-------------|------------------------------|--------------------------------------|
| `items`     | `MediaItem[]`                | Result items                         |
| `total`     | `int`                        | Total count when available           |
| `has_more`  | `bool`                       | Whether another page is available    |
| `page`      | `int`                        | Current numeric page                 |
| `per_page`  | `int`                        | Requested page size                  |
| `next_page` | `int\|string\|array\|null`   | Next page number or cursor token     |

### Check Driver Capabilities

```php
$driver = $manager->driver('bing');

$driver->source();        // 'bing'
$driver->searchable();    // false — Bing does not support keyword search
$driver->requiresApiKey(); // false
```

### Laravel

If you are using a full Laravel application, the required Illuminate packages are already present.

Publish the config file:

```bash
php artisan vendor:publish --tag="media-bridge-config"
```

Configure your API keys in `config/media-bridge.php` or via environment variables:

```env
UNSPLASH_API_KEY=your-key
PEXELS_API_KEY=your-key
PIXABAY_API_KEY=your-key
```

Use the facade:

```php
use Chuoke\MediaBridge\Laravel\Media;

$result = Media::driver('unsplash')->search('sunset');
$result = Media::search('cat'); // uses default driver
```

When the package runs inside a Laravel application, outgoing requests use Laravel's `Http` client automatically. That allows Laravel-side tooling such as Telescope to observe these requests. Outside Laravel, the package continues to use Guzzle directly.

## MediaItem Fields

| Field          | Type           | Description                              |
|----------------|----------------|------------------------------------------|
| `source`       | `string`       | Driver name, e.g. `'unsplash'`           |
| `source_id`    | `string`       | Stable ID from the source platform       |
| `media_type`   | `string`       | `'photo'` or `'video'`                   |
| `url`          | `string`       | Best available direct media URL          |
| `thumb_url`    | `string`       | Thumbnail URL                            |
| `variants`     | `array`        | Available media variants                 |
| `download_url` | `string\|null` | Download trigger URL (Unsplash)          |
| `title`        | `string\|null` | Title if provided by source              |
| `description`  | `string\|null` | Description if provided by source        |
| `author_name`  | `string\|null` | Photographer / uploader name             |
| `author_url`   | `string\|null` | Link to author profile                   |
| `license`      | `string`       | License identifier, e.g. `'cc0'`         |
| `width`        | `int\|null`    | Image width in pixels                    |
| `height`       | `int\|null`    | Image height in pixels                   |
| `color`        | `string\|null` | Dominant color hex (if available)        |
| `tags`         | `array`        | Keyword tags                             |
| `display_date` | `string\|null` | Date string `YYYY-MM-DD`                 |

## Testing

```bash
composer test
```

The default test suite uses mocked HTTP responses and does not require API keys or network access.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Credits

- [chuoke](https://github.com/chuoke)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
