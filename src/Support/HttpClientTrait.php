<?php

declare(strict_types=1);

namespace Chuoke\MediaBridge\Support;

use Chuoke\MediaBridge\Exceptions\MediaException;
use Chuoke\MediaBridge\Exceptions\RequestFailedException;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use JsonException;
use Psr\Http\Message\ResponseInterface;

trait HttpClientTrait
{
    protected mixed $httpClient = null;

    protected function baseUri(): string
    {
        return $this->baseUri;
    }

    protected function defaultHeaders(): array
    {
        return [];
    }

    protected function httpClient(): object
    {
        if ($this->httpClient !== null) {
            return $this->httpClient;
        }

        if ($this->shouldUseLaravelHttpClient()) {
            return $this->httpClient = $this->createLaravelHttpClient();
        }

        return $this->httpClient = new Client([
            'base_uri' => $this->baseUri(),
            'headers' => $this->defaultHeaders(),
            'http_errors' => false,
        ]);
    }

    protected function parseJson(ResponseInterface $response): array
    {
        return $this->decodeJson((string) $response->getBody());
    }

    protected function checkResponse(ResponseInterface $response, string $sourceName): void
    {
        $status = $response->getStatusCode();

        if ($status >= 200 && $status < 300) {
            return;
        }

        $body = (string) $response->getBody();
        $decoded = $this->tryDecodeJson($body);
        $message = $this->extractErrorMessage($decoded ?? []) ?: $body;

        throw new RequestFailedException(
            "Request to [{$sourceName}] failed with status {$status}: {$message}",
            $status
        );
    }

    protected function extractErrorMessage(array $error): string
    {
        return '';
    }

    private function shouldUseLaravelHttpClient(): bool
    {
        return class_exists(\Illuminate\Support\Facades\Http::class);
    }

    private function createLaravelHttpClient(): object
    {
        $baseUri = $this->baseUri();
        $headers = $this->defaultHeaders();

        return new class($baseUri, $headers)
        {
            public function __construct(
                private readonly string $baseUri,
                private readonly array $headers,
            ) {
            }

            public function get(string $uri, array $options = []): ResponseInterface
            {
                $response = \Illuminate\Support\Facades\Http::baseUrl($this->baseUri)
                    ->withHeaders($this->headers)
                    ->withOptions(['http_errors' => false])
                    ->send('GET', $uri, $options);

                return new Response(
                    $response->status(),
                    $response->headers(),
                    $response->body()
                );
            }
        };
    }

    private function decodeJson(string $body): array
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new MediaException('Failed to decode JSON response: ' . $e->getMessage(), 0, $e);
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function tryDecodeJson(string $body): ?array
    {
        if ($body === '') {
            return null;
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
