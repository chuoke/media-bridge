<?php

use Chuoke\MediaBridge\Data\MediaItem;
use Chuoke\MediaBridge\Data\MediaResult;
use Chuoke\MediaBridge\Drivers\NasaDriver;

function nasaDriver(array $responses): NasaDriver
{
    return driverWithHttpResponses(new NasaDriver(), $responses);
}

function nasaSearchFixture(string $path, int $items = 1): array
{
    $fixture = mediaFixture($path);
    $fixture['collection']['items'] = array_slice($fixture['collection']['items'], 0, $items);

    return $fixture;
}

test('NASA search returns MediaResult', function () {
    $result = nasaDriver([
        jsonResponse(nasaSearchFixture('nasa/search-images.json')),
        jsonResponse(mediaFixture('nasa/asset-image.json')),
    ])->search('moon', 1, 1);

    expect($result)->toBeInstanceOf(MediaResult::class);
    expect($result->items)->toBeArray();
    expect($result->items)->toHaveCount(1);
});

test('NASA MediaItem has required fields', function () {
    $result = nasaDriver([
        jsonResponse(nasaSearchFixture('nasa/search-images.json')),
        jsonResponse(mediaFixture('nasa/asset-image.json')),
    ])->search('apollo', 1, 1);

    expect($result->items)->not->toBeEmpty();

    $item = $result->items[0];
    expect($item)->toBeInstanceOf(MediaItem::class);
    expect($item->source)->toBe('nasa');
    expect($item->source_id)->toBe('NHQ201907180120');
    expect($item->media_type)->toBe('photo');
    expect($item->url)->toBeString()->not->toBeEmpty();
    expect($item->thumb_url)->toBeString()->not->toBeEmpty();
    expect($item->license)->toBe('cc0');
    expect($item->variants)->toBeArray()->not->toBeEmpty();
    expect($item->title)->toBe('Apollo 11 50th Anniversary Celebration');
    expect($item->author_name)->toBe('NASA/Connie Moore');
    expect($item->display_date)->toBe('2019-07-18');
});

test('NASA video search returns direct video assets', function () {
    $result = nasaDriver([
        jsonResponse(nasaSearchFixture('nasa/search-videos.json')),
        jsonResponse(mediaFixture('nasa/asset-video.json')),
    ])->search('moon', 1, 1, ['media_type' => 'video']);

    expect($result->items)->not->toBeEmpty();
    expect($result->items[0]->media_type)->toBe('video');
    expect($result->items[0]->url)->toMatch('/\.mp4$/i');
    expect($result->items[0]->variants)->not->toBeEmpty();
    expect($result->items[0]->source_id)->toBe('KSC-19850101-MH-NAS01-0001-Apollo_11_Buzz_Aldrin_Experts-B_2141');
});
