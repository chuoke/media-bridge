<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Media Driver
    |--------------------------------------------------------------------------
    |
    | The default media source driver to use when no driver is specified.
    | Supported: "bing", "unsplash", "pexels", "pixabay", "wikimedia", "nasa"
    |
    */

    'default' => env('MEDIA_BRIDGE_DRIVER', 'bing'),

    /*
    |--------------------------------------------------------------------------
    | Driver Configurations
    |--------------------------------------------------------------------------
    */

    'drivers' => [

        'bing' => [
            // No API key required
        ],

        'unsplash' => [
            'api_key' => env('UNSPLASH_API_KEY', ''),
        ],

        'pexels' => [
            'api_key' => env('PEXELS_API_KEY', ''),
        ],

        'pixabay' => [
            'api_key' => env('PIXABAY_API_KEY', ''),
        ],

        'wikimedia' => [
            // No API key required
        ],

        'nasa' => [
            // No API key required
        ],

    ],

];
