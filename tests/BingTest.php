<?php

use Chuoke\MediaBridge\Data\MediaItem;
use Chuoke\MediaBridge\Data\MediaResult;
use Chuoke\MediaBridge\Data\MediaVariant;
use Chuoke\MediaBridge\Drivers\BingDriver;

function bingDriver(array $responses): BingDriver
{
    return driverWithHttpResponses(new BingDriver(), $responses);
}

test('Bing search returns MediaResult', function () {
    $result = bingDriver([
        jsonResponse(mediaFixture('bing/daily-images.json')),
        jsonResponse(mediaFixture('bing/daily-images.json')),
    ])->search();

    expect($result)->toBeInstanceOf(MediaResult::class);
    expect($result->items)->toBeArray();
    expect($result->items)->toHaveCount(8);
    expect($result->total)->toBe(8);
    expect($result->page)->toBe(1);
    expect($result->hasMore)->toBeFalse();
    expect($result->nextPage)->toBeNull();
});

test('Bing paginates deduplicated archive items locally', function () {
    $result = bingDriver([
        jsonResponse(mediaFixture('bing/daily-images.json')),
        jsonResponse(mediaFixture('bing/daily-images.json')),
    ])->search(page: 1, perPage: 4);

    expect($result)->toBeInstanceOf(MediaResult::class);
    expect($result->items)->toHaveCount(4);
    expect($result->page)->toBe(1);
    expect($result->total)->toBe(8);
    expect($result->hasMore)->toBeTrue();
    expect($result->nextPage)->toBe(2);
});

test('Bing second local page returns the remaining deduplicated items', function () {
    $result = bingDriver([
        jsonResponse(mediaFixture('bing/daily-images.json')),
        jsonResponse(mediaFixture('bing/daily-images.json')),
    ])->search(page: 2, perPage: 4);

    expect($result)->toBeInstanceOf(MediaResult::class);
    expect($result->items)->toHaveCount(4);
    expect($result->page)->toBe(2);
    expect($result->total)->toBe(8);
    expect($result->hasMore)->toBeFalse();
    expect($result->nextPage)->toBeNull();
});

test('Bing MediaItem has required fields', function () {
    $result = bingDriver([
        jsonResponse(mediaFixture('bing/daily-images.json')),
        jsonResponse(mediaFixture('bing/daily-images.json')),
    ])->search();

    expect($result->items)->not->toBeEmpty();

    $item = $result->items[0];
    expect($item)->toBeInstanceOf(MediaItem::class);
    expect($item->source)->toBe('bing');
    expect($item->source_id)->toBe('303860a83220a2ee4808df5b9a09ba67');
    expect($item->media_type)->toBe('photo');
    expect($item->url)->toBe('https://www.bing.com/th?id=OHR.WildflowerValley_EN-US6579657743_1920x1080.jpg&pid=hp&w=3840&rs=1&c=4');
    expect($item->thumb_url)->toBe('https://www.bing.com/th?id=OHR.WildflowerValley_EN-US6579657743_1920x1080.jpg&pid=hp&w=384&rs=1&c=4');
    expect($item->license)->toBe('unknown');
    expect($item->tags)->toBeArray();
    expect($item->variants)->toBeArray()->not->toBeEmpty();
    expect($item->variants[0])->toBeInstanceOf(MediaVariant::class);
    expect($item->toArray()['variants'][0])->toBeArray();
    expect($item->title)->toBe('Wildflower bloom, Central Valley, California');
    expect($item->author_name)->toBe('Jeff Lewis/Tandem Stills + Motion');
    expect($item->width)->toBe(1920);
    expect($item->height)->toBe(1080);
    expect($item->display_date)->toBe('2026-04-02');
});
