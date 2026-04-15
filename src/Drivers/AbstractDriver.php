<?php

declare(strict_types=1);

namespace Chuoke\MediaBridge\Drivers;

use Chuoke\MediaBridge\Contracts\MediaProviderInterface;
use Chuoke\MediaBridge\Support\HttpClientTrait;

abstract class AbstractDriver implements MediaProviderInterface
{
    use HttpClientTrait;

    protected string $baseUri = '';

    abstract public function source(): string;

    public function requiresApiKey(): bool
    {
        return true;
    }

    public function searchable(): bool
    {
        return true;
    }
}
