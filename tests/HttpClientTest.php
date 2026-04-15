<?php

use Chuoke\MediaBridge\Data\MediaResult;
use Chuoke\MediaBridge\Drivers\AbstractDriver;
use Chuoke\MediaBridge\Exceptions\MediaException;
use Chuoke\MediaBridge\Exceptions\RequestFailedException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

function fakeHttpClient(array $responses): Client
{
    return new Client([
        'handler' => HandlerStack::create(new MockHandler($responses)),
        'http_errors' => false,
    ]);
}

function fakeDriver(Client $client): AbstractDriver
{
    return new class($client) extends AbstractDriver
    {
        protected string $baseUri = 'https://example.test/';

        public function __construct(Client $client)
        {
            $this->httpClient = $client;
        }

        public function source(): string
        {
            return 'fake';
        }

        public function search(string $query = '', int $page = 1, int $perPage = 20, array $extras = []): MediaResult
        {
            return new MediaResult([], 0, false, $page, $perPage);
        }

        public function fetch(): array
        {
            $response = $this->httpClient()->get('/');
            $this->checkResponse($response, $this->source());

            return $this->parseJson($response);
        }

        protected function extractErrorMessage(array $error): string
        {
            return $error['error'] ?? '';
        }
    };
}

test('http client errors are wrapped in request failed exception', function () {
    $driver = fakeDriver(fakeHttpClient([
        new Response(401, ['Content-Type' => 'application/json'], json_encode([
            'error' => 'invalid token',
        ])),
    ]));

    $driver->fetch();
})->throws(RequestFailedException::class, 'Request to [fake] failed with status 401: invalid token');

test('invalid json throws media exception', function () {
    $driver = fakeDriver(fakeHttpClient([
        new Response(200, ['Content-Type' => 'application/json'], '{invalid-json'),
    ]));

    $driver->fetch();
})->throws(MediaException::class, 'Failed to decode JSON response');
