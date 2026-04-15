<?php

use Chuoke\MediaBridge\Data\MediaItem;
use Chuoke\MediaBridge\Data\MediaResult;
use Chuoke\MediaBridge\Drivers\PixabayDriver;

function pixabayDriver(array $responses): PixabayDriver
{
    return driverWithHttpResponses(new PixabayDriver('test-key'), $responses);
}

test('Pixabay search returns MediaResult', function () {
    $result = pixabayDriver([
        jsonResponse(mediaFixture('pixabay/images.json')),
    ])->search();

    expect($result)->toBeInstanceOf(MediaResult::class);
    expect($result->items)->toBeArray();
    expect($result->items)->toHaveCount(20);
});

test('Pixabay MediaItem has required fields', function () {
    $result = pixabayDriver([
        jsonResponse(mediaFixture('pixabay/images.json')),
    ])->search();

    expect($result->items)->not->toBeEmpty();

    $item = $result->items[0];
    expect($item)->toBeInstanceOf(MediaItem::class);
    expect($item->source)->toBe('pixabay');
    expect($item->source_id)->toBe('10211003');
    expect($item->media_type)->toBe('photo');
    expect($item->url)->toBeString()->not->toBeEmpty();
    expect($item->thumb_url)->toBeString()->not->toBeEmpty();
    expect($item->license)->toBe('cc0');
    expect($item->tags)->toBeArray();
    expect($item->variants)->toBeArray()->not->toBeEmpty();
    expect($item->author_name)->toBe('anselmo7511');
    expect($item->width)->toBe(4288);
    expect($item->height)->toBe(2848);
});

test('Pixabay search with query returns results', function () {
    $result = pixabayDriver([
        jsonResponse(mediaFixture('pixabay/images.json')),
    ])->search('nature', 1, 5);

    expect($result)->toBeInstanceOf(MediaResult::class);
    expect($result->items)->not->toBeEmpty();
});

test('Pixabay video search returns video variants', function () {
    $result = pixabayDriver([
        jsonResponse(mediaFixture('pixabay/videos.json')),
    ])->search('', 1, 5, ['media_type' => 'video']);

    expect($result)->toBeInstanceOf(MediaResult::class);
    expect($result->items)->not->toBeEmpty();

    $item = $result->items[0];
    expect($item->source_id)->toBe('344927');
    expect($item->media_type)->toBe('video');
    expect($item->url)->toMatch('/\.mp4$/i');
    expect($item->variants)->toBeArray()->not->toBeEmpty();
});
