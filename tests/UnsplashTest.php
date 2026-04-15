<?php

use Chuoke\MediaBridge\Data\MediaItem;
use Chuoke\MediaBridge\Data\MediaResult;
use Chuoke\MediaBridge\Drivers\UnsplashDriver;

function unsplashDriver(array $responses): UnsplashDriver
{
    return driverWithHttpResponses(new UnsplashDriver('test-key'), $responses);
}

test('Unsplash editorial feed returns MediaResult', function () {
    $result = unsplashDriver([
        jsonResponse(mediaFixture('unsplash/photos.json')),
    ])->search();

    expect($result)->toBeInstanceOf(MediaResult::class);
    expect($result->items)->toBeArray();
    expect($result->items)->toHaveCount(10);
});

test('Unsplash MediaItem has required fields', function () {
    $result = unsplashDriver([
        jsonResponse(mediaFixture('unsplash/photos.json')),
    ])->search();

    expect($result->items)->not->toBeEmpty();

    $item = $result->items[0];
    expect($item)->toBeInstanceOf(MediaItem::class);
    expect($item->source)->toBe('unsplash');
    expect($item->source_id)->toBe('Z38ADyUhN6s');
    expect($item->media_type)->toBe('photo');
    expect($item->url)->toBeString()->not->toBeEmpty();
    expect($item->thumb_url)->toBeString()->not->toBeEmpty();
    expect($item->license)->toBe('unsplash');
    expect($item->download_url)->toBeString()->not->toBeEmpty();
    expect($item->width)->toBe(5405);
    expect($item->height)->toBe(8103);
    expect($item->variants)->toBeArray()->not->toBeEmpty();
    expect($item->author_name)->toBe('Microsoft Copilot');
    expect($item->display_date)->toBe('2026-03-12');
});

test('Unsplash search with query returns results', function () {
    $result = unsplashDriver([
        jsonResponse(mediaFixture('unsplash/search-photos.json')),
    ])->search('ocean', 1, 5);

    expect($result)->toBeInstanceOf(MediaResult::class);
    expect($result->items)->not->toBeEmpty();
    expect($result->items[0]->source_id)->toBe('qUJ8fgoaLTg');
    expect($result->total)->toBe(10010);
});
