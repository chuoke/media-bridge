<?php

use Chuoke\MediaBridge\Data\MediaItem;
use Chuoke\MediaBridge\Data\MediaResult;
use Chuoke\MediaBridge\Drivers\WikimediaDriver;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

function wikimediaPage(int $id): array
{
    return [
        'pageid' => $id,
        'title' => 'File:Example-' . $id . '.jpg',
        'imageinfo' => [[
            'url' => 'https://upload.wikimedia.org/example-' . $id . '.jpg',
            'thumburl' => 'https://upload.wikimedia.org/thumb/example-' . $id . '.jpg',
            'thumbwidth' => 400,
            'thumbheight' => 300,
            'user' => 'Wikimedia Author',
            'width' => 1600,
            'height' => 1200,
            'descriptionurl' => 'https://commons.wikimedia.org/wiki/File:Example-' . $id . '.jpg',
            'extmetadata' => [
                'LicenseShortName' => ['value' => 'CC BY 4.0'],
                'Artist' => ['value' => 'Wikimedia Author'],
                'ImageDescription' => ['value' => 'Wikimedia description'],
                'DateTimeOriginal' => ['value' => '2026-04-10'],
                'Categories' => ['value' => 'Nature|Test'],
            ],
        ]],
    ];
}

function wikimediaResponse(int $id, array $continue = []): array
{
    return array_filter([
        'query' => [
            'pages' => [
                (string) $id => wikimediaPage($id),
            ],
            'searchinfo' => ['totalhits' => 2],
        ],
        'continue' => $continue ?: null,
    ]);
}

function wikimediaDriver(array $responses): WikimediaDriver
{
    return driverWithHttpResponses(new WikimediaDriver(), $responses);
}

function wikimediaDriverWithHistory(array $responses, array &$history): WikimediaDriver
{
    $history = [];
    $handlerStack = HandlerStack::create(new MockHandler($responses));
    $handlerStack->push(Middleware::history($history));

    $driver = new WikimediaDriver();
    $property = new ReflectionProperty($driver, 'httpClient');
    $property->setValue($driver, new Client([
        'handler' => $handlerStack,
        'http_errors' => false,
    ]));

    return $driver;
}

test('Wikimedia browse returns MediaResult', function () {
    $result = wikimediaDriver([
        jsonResponse(wikimediaResponse(1)),
    ])->search();

    expect($result)->toBeInstanceOf(MediaResult::class);
    expect($result->items)->toBeArray();
});

test('Wikimedia MediaItem has required fields', function () {
    $result = wikimediaDriver([
        jsonResponse(wikimediaResponse(1)),
    ])->search();

    expect($result->items)->not->toBeEmpty();

    $item = $result->items[0];
    expect($item)->toBeInstanceOf(MediaItem::class);
    expect($item->source)->toBe('wikimedia');
    expect($item->source_id)->toBeString()->not->toBeEmpty();
    expect($item->media_type)->toBe('photo');
    expect($item->url)->toBeString()->not->toBeEmpty();
    expect($item->thumb_url)->toBeString()->not->toBeEmpty();
    expect($item->variants)->toBeArray()->not->toBeEmpty();
});

test('Wikimedia search with query returns results', function () {
    $result = wikimediaDriver([
        jsonResponse(wikimediaResponse(1)),
    ])->search('cat', 1, 5);

    expect($result)->toBeInstanceOf(MediaResult::class);
    expect($result->items)->not->toBeEmpty();
    expect($result->total)->toBeInt();
});

test('Wikimedia browse pagination advances to the next page', function () {
    $pageOne = wikimediaDriver([
        jsonResponse(wikimediaResponse(1, [
            'gaicontinue' => 'next',
            'continue' => 'gaicontinue||',
        ])),
    ])->search('', 1, 3);
    $pageTwo = wikimediaDriver([
        jsonResponse(wikimediaResponse(2)),
    ])->search('', 2, 3, ['cursor' => $pageOne->nextPage]);

    $pageOneIds = array_map(fn (MediaItem $item) => $item->source_id, $pageOne->items);
    $pageTwoIds = array_map(fn (MediaItem $item) => $item->source_id, $pageTwo->items);

    expect($pageTwoIds)->not->toEqual($pageOneIds);
    expect($pageOne->nextPage)->toBe([
        'gaicontinue' => 'next',
        'continue' => 'gaicontinue||',
    ]);
});

test('Wikimedia browse does not send unsupported allimages MIME parameter', function () {
    $history = [];
    $driver = wikimediaDriverWithHistory([
        jsonResponse(wikimediaResponse(1)),
    ], $history);

    $driver->search();

    parse_str($history[0]['request']->getUri()->getQuery(), $query);

    expect($query)->not->toHaveKey('gaimit');
    expect($query)->not->toHaveKey('gaimime');
    expect($query['generator'])->toBe('allimages');
});

test('Wikimedia video browse uses search filetype filter', function () {
    $history = [];
    $driver = wikimediaDriverWithHistory([
        jsonResponse(wikimediaResponse(1)),
    ], $history);

    $driver->search('', 1, 3, ['media_type' => 'video']);

    parse_str($history[0]['request']->getUri()->getQuery(), $query);

    expect($query['generator'])->toBe('search');
    expect($query['gsrsearch'])->toBe('filetype:video');
});

test('Wikimedia browse sends cursor when provided', function () {
    $history = [];
    $driver = wikimediaDriverWithHistory([
        jsonResponse(wikimediaResponse(2)),
    ], $history);

    $driver->search('', 2, 3, [
        'cursor' => [
            'gaicontinue' => 'next',
            'continue' => 'gaicontinue||',
        ],
    ]);

    parse_str($history[0]['request']->getUri()->getQuery(), $query);

    expect($query['gaicontinue'])->toBe('next');
    expect($query['continue'])->toBe('gaicontinue||');
});
