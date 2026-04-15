<?php

declare(strict_types=1);

namespace Chuoke\MediaBridge\Laravel;

use Chuoke\MediaBridge\MediaManager;
use Illuminate\Support\ServiceProvider;

class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->configPath(), 'media-bridge');

        $this->app->singleton(MediaManager::class, function ($app) {
            return new MediaManager($app['config']->get('media-bridge', []));
        });

        $this->app->alias(MediaManager::class, 'media-bridge');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->configPath() => config_path('media-bridge.php'),
            ], 'media-bridge-config');
        }
    }

    protected function configPath(): string
    {
        return __DIR__ . '/../../config/media-bridge.php';
    }
}
