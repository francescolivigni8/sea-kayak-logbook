<?php

return [
    'media_disk' => env('KAYAK_MEDIA_DISK', 'public'),
    'media_temporary_urls' => (bool) env('KAYAK_MEDIA_TEMPORARY_URLS', false),
    'media_temporary_url_minutes' => (int) env('KAYAK_MEDIA_TEMPORARY_URL_MINUTES', 30),
    'public_profiles_enabled' => (bool) env('KAYAK_PUBLIC_PROFILES_ENABLED', false),
    'noindex' => (bool) env('KAYAK_NOINDEX', true),
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
                'auth_value_prefix' => env('STORMGLASS_AUTH_VALUE_PREFIX', ''),
                'source' => env('STORMGLASS_SOURCE', 'sg'),
                'timeout' => (int) env('STORMGLASS_TIMEOUT', 10),
                'cache_seconds' => (int) env('STORMGLASS_CACHE_SECONDS', 3600),
                'params' => [
                    'windSpeed',
                    'gust',
                    'windDirection',
                    'precipitation',
                    'cloudCover',
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
            'open_meteo' => [
                'enabled' => (bool) env('OPEN_METEO_ENABLED', true),
                'api_key' => env('OPEN_METEO_API_KEY'),
                'base_url' => env('OPEN_METEO_BASE_URL', 'https://api.open-meteo.com/v1/forecast'),
                'marine_base_url' => env('OPEN_METEO_MARINE_BASE_URL', 'https://marine-api.open-meteo.com/v1/marine'),
                'timeout' => (int) env('OPEN_METEO_TIMEOUT', 10),
                'cache_seconds' => (int) env('OPEN_METEO_CACHE_SECONDS', 3600),
            ],
            'met_no' => [
                'enabled' => (bool) env('MET_NO_ENABLED', false),
                'base_url' => env('MET_NO_BASE_URL', 'https://api.met.no/weatherapi/locationforecast/2.0/complete'),
                'user_agent' => env('MET_NO_USER_AGENT', 'Your Kayaking Journal/1.0 hello@yourkayakingjournal.com'),
                'timeout' => (int) env('MET_NO_TIMEOUT', 10),
                'cache_seconds' => (int) env('MET_NO_CACHE_SECONDS', 3600),
            ],
        ],
    ],
];
