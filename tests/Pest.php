<?php

use Chuoke\MediaBridge\MediaManager;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\ResponseInterface;

function mediaManager(): MediaManager
{
    return new MediaManager([
        'default' => 'bing',
        'drivers' => [
            'bing' => [],
            'unsplash' => ['api_key' => 'test-unsplash-key'],
            'pexels' => ['api_key' => 'test-pexels-key'],
            'pixabay' => ['api_key' => 'test-pixabay-key'],
            'wikimedia' => [],
            'nasa' => [],
        ],
    ]);
}

function mockHttpClient(array $responses): Client
{
    return new Client([
        'handler' => HandlerStack::create(new MockHandler($responses)),
        'http_errors' => false,
    ]);
}

function jsonResponse(array $body, int $status = 200): ResponseInterface
{
    return new GuzzleHttp\Psr7\Response(
        $status,
        ['Content-Type' => 'application/json'],
        json_encode($body)
    );
}

function driverWithHttpResponses(object $driver, array $responses): object
{
    $property = new ReflectionProperty($driver, 'httpClient');
    $property->setValue($driver, mockHttpClient($responses));

    return $driver;
}

function mediaFixture(string $path): array
{
    return json_decode(
        file_get_contents(__DIR__ . '/Fixtures/' . $path),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
}
