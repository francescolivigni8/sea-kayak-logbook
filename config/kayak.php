<?php

return [
    'media_disk' => env('KAYAK_MEDIA_DISK', 'public'),
    'owner_emails' => collect(explode(',', (string) env('KAYAK_OWNER_EMAILS', '')))
        ->map(fn (string $email) => trim(strtolower($email)))
        ->filter()
        ->values()
        ->all(),
    'weather' => [
        'providers' => [
            'stormglass' => [
                'api_key' => env('STORMGLASS_API_KEY'),
                'base_url' => env('STORMGLASS_BASE_URL', 'https://api.stormglass.io/v2/weather/point'),
                'tide_extremes_url' => env('STORMGLASS_TIDE_EXTREMES_URL', 'https://api.stormglass.io/v2/tide/extremes/point'),
                'auth_header' => env('STORMGLASS_AUTH_HEADER', 'Authorization'),
                'source' => env('STORMGLASS_SOURCE', 'sg'),
                'timeout' => (int) env('STORMGLASS_TIMEOUT', 10),
                'params' => [
                    'windSpeed',
                    'gust',
                    'windDirection',
                    'precipitation',
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
