<?php

use Chuoke\MediaBridge\Contracts\MediaProviderInterface;

test('manager can resolve a driver', function () {
    $driver = mediaManager()->driver('bing');

    expect($driver)->toBeInstanceOf(MediaProviderInterface::class);
    expect($driver->source())->toBe('bing');
    expect($driver->requiresApiKey())->toBeFalse();
    expect($driver->searchable())->toBeFalse();
});

test('searchable drivers return true', function () {
    $manager = mediaManager();

    expect($manager->driver('unsplash')->searchable())->toBeTrue();
    expect($manager->driver('pexels')->searchable())->toBeTrue();
    expect($manager->driver('pixabay')->searchable())->toBeTrue();
});

test('manager throws on unknown driver', function () {
    mediaManager()->driver('unknown_driver');
})->throws(InvalidArgumentException::class);
