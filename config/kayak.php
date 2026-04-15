<?php

return [
    'media_disk' => env('KAYAK_MEDIA_DISK', 'public'),
    'weather' => [
        'providers' => [
            'stormglass' => [
                'api_key' => env('STORMGLASS_API_KEY'),
                'base_url' => env('STORMGLASS_BASE_URL', 'https://api.stormglass.io/v2/weather/point'),
                'auth_header' => env('STORMGLASS_AUTH_HEADER', 'Authorization'),
                'source' => env('STORMGLASS_SOURCE', 'sg'),
                'timeout' => (int) env('STORMGLASS_TIMEOUT', 10),
                'params' => [
                    'windSpeed',
                    'gust',
                    'windDirection',
                    'airTemperature',
                    'waterTemperature',
                    'visibility',
                    'currentSpeed',
                    'currentDirection',
                    'waveHeight',
                    'swellHeight',
                    'swellPeriod',
                    'swellDirection',
                ],
            ],
        ],
    ],
];
