<?php

declare(strict_types=1);

namespace Chuoke\MediaBridge\Laravel;

use Chuoke\MediaBridge\MediaManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Chuoke\MediaBridge\Contracts\MediaProviderInterface driver(string $name = null)
 * @method static \Chuoke\MediaBridge\Data\MediaResult search(string $query = '', int $page = 1, int $perPage = 20, array $extras = [])
 * @method static \Chuoke\MediaBridge\MediaManager extend(string $name, callable $creator)
 *
 * @see \Chuoke\MediaBridge\MediaManager
 */
class Media extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MediaManager::class;
    }
}
