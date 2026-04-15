<?php

use Chuoke\MediaBridge\Data\MediaItem;
use Chuoke\MediaBridge\Data\MediaResult;
use Chuoke\MediaBridge\Data\MediaVariant;
use Chuoke\MediaBridge\Drivers\PexelsDriver;

function pexelsDriver(array $responses): PexelsDriver
{
    return driverWithHttpResponses(new PexelsDriver('test-key'), $responses);
}

test('Pexels search returns MediaResult', function () {
    $result = pexelsDriver([
        jsonResponse(mediaFixture('pexels/curated-photos.json')),
    ])->search();

    expect($result)->toBeInstanceOf(MediaResult::class);
    expect($result->items)->toBeArray();
    expect($result->items)->toHaveCount(15);
});

test('Pexels MediaItem has required fields', function () {
    $result = pexelsDriver([
        jsonResponse(mediaFixture('pexels/curated-photos.json')),
    ])->search();

    expect($result->items)->not->toBeEmpty();

    $item = $result->items[0];
    expect($item)->toBeInstanceOf(MediaItem::class);
    expect($item->source)->toBe('pexels');
    expect($item->source_id)->toBe('29432267');
    expect($item->media_type)->toBe('photo');
    expect($item->url)->toBeString()->not->toBeEmpty();
    expect($item->thumb_url)->toBeString()->not->toBeEmpty();
    expect($item->license)->toBe('pexels');
    expect($item->width)->toBe(2560);
    expect($item->height)->toBe(3840);
    expect($item->variants)->toBeArray()->not->toBeEmpty();
    expect($item->author_name)->toBe('Henry Acevedo');
});

test('Pexels search with query returns results', function () {
    $result = pexelsDriver([
        jsonResponse(mediaFixture('pexels/search-photos.json')),
    ])->search('mountain', 1, 5);

    expect($result)->toBeInstanceOf(MediaResult::class);
    expect($result->items)->not->toBeEmpty();
    expect($result->items[0]->source_id)->toBe('12377231');
    expect($result->page)->toBe(1);
    expect($result->perPage)->toBe(5);
    expect($result->nextPage)->toBe(2);
});

test('Pexels video search returns video variants', function () {
    $result = pexelsDriver([
        jsonResponse(mediaFixture('pexels/popular-videos.json')),
    ])->search('', 1, 5, ['media_type' => 'video']);

    expect($result)->toBeInstanceOf(MediaResult::class);
    expect($result->items)->not->toBeEmpty();

    $item = $result->items[0];
    expect($item->source_id)->toBe('6963395');
    expect($item->media_type)->toBe('video');
    expect($item->url)->toMatch('/\.mp4$/i');
    expect($item->variants)->toBeArray()->not->toBeEmpty();
    expect($item->variants[0])->toBeInstanceOf(MediaVariant::class);
});
