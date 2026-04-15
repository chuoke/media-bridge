<?php

declare(strict_types=1);

namespace Chuoke\MediaBridge;

use Chuoke\MediaBridge\Contracts\MediaProviderInterface;
use Chuoke\MediaBridge\Drivers\BingDriver;
use Chuoke\MediaBridge\Drivers\NasaDriver;
use Chuoke\MediaBridge\Drivers\PexelsDriver;
use Chuoke\MediaBridge\Drivers\PixabayDriver;
use Chuoke\MediaBridge\Drivers\UnsplashDriver;
use Chuoke\MediaBridge\Drivers\WikimediaDriver;
use InvalidArgumentException;

class MediaManager
{
    /** @var MediaProviderInterface[] */
    protected array $resolved = [];

    /** @var array<string, callable> */
    protected array $customCreators = [];

    public function __construct(protected array $config = [])
    {
    }

    /**
     * Get a driver instance by name.
     */
    public function driver(?string $name = null): MediaProviderInterface
    {
        $name = $name ?? $this->getDefaultDriver();

        if ($name === null) {
            throw new InvalidArgumentException('No driver specified and no default driver configured.');
        }

        return $this->resolved[$name] ??= $this->resolve($name);
    }

    /**
     * Register a custom driver creator.
     */
    public function extend(string $name, callable $creator): static
    {
        $this->customCreators[$name] = $creator;

        return $this;
    }

    /**
     * Forget a resolved driver instance (useful for re-creating with new config).
     */
    public function forgetDriver(string $name): static
    {
        unset($this->resolved[$name]);

        return $this;
    }

    public function getDefaultDriver(): ?string
    {
        return $this->config['default'] ?? null;
    }

    protected function resolve(string $name): MediaProviderInterface
    {
        if (isset($this->customCreators[$name])) {
            return ($this->customCreators[$name])($this->getDriverConfig($name), $this->config);
        }

        $method = 'create' . ucfirst($name) . 'Driver';

        if (! method_exists($this, $method)) {
            throw new InvalidArgumentException("Driver [{$name}] is not supported.");
        }

        return $this->{$method}($this->getDriverConfig($name));
    }

    protected function getDriverConfig(string $name): array
    {
        return $this->config['drivers'][$name] ?? [];
    }

    protected function createBingDriver(array $config): BingDriver
    {
        return new BingDriver();
    }

    protected function createUnsplashDriver(array $config): UnsplashDriver
    {
        $this->requireKey($config, 'unsplash');

        return new UnsplashDriver($config['api_key']);
    }

    protected function createPexelsDriver(array $config): PexelsDriver
    {
        $this->requireKey($config, 'pexels');

        return new PexelsDriver($config['api_key']);
    }

    protected function createPixabayDriver(array $config): PixabayDriver
    {
        $this->requireKey($config, 'pixabay');

        return new PixabayDriver($config['api_key']);
    }

    protected function createWikimediaDriver(array $config): WikimediaDriver
    {
        return new WikimediaDriver();
    }

    protected function createNasaDriver(array $config): NasaDriver
    {
        return new NasaDriver();
    }

    protected function requireKey(array $config, string $driver): void
    {
        if (empty($config['api_key'])) {
            throw new InvalidArgumentException("Driver [{$driver}] requires an api_key in its configuration.");
        }
    }

    /**
     * Proxy calls to the default driver.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->driver()->$method(...$parameters);
    }
}
