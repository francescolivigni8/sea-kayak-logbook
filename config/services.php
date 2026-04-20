<?php

$maptilerKey = env('MAPTILER_API_KEY');
$mapProvider = env('MAP_PROVIDER', $maptilerKey ? 'maptiler' : 'open');
$useMaptiler = $mapProvider === 'maptiler' && filled($maptilerKey);
$maptilerAttribution = 'Map data © OpenStreetMap contributors | Map style © MapTiler';

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'maps' => [
        'provider' => $mapProvider,
        'maptiler_key' => $maptilerKey,
        'styles' => [
            'chart' => [
                'label' => 'Chart',
                'url' => env(
                    'MAP_CHART_TILE_URL',
                    $useMaptiler
                        ? 'https://api.maptiler.com/maps/topo-v4/256/{z}/{x}/{y}.png?key='.$maptilerKey
                        : 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
                ),
                'attribution' => env(
                    'MAP_CHART_ATTRIBUTION',
                    $useMaptiler
                        ? $maptilerAttribution
                        : 'Map data © OpenStreetMap contributors, SRTM | Map style © OpenTopoMap',
                ),
                'max_zoom' => (int) env('MAP_CHART_MAX_ZOOM', $useMaptiler ? 20 : 17),
            ],
            'clean' => [
                'label' => 'Clean',
                'url' => env(
                    'MAP_CLEAN_TILE_URL',
                    $useMaptiler
                        ? 'https://api.maptiler.com/maps/streets-v2/256/{z}/{x}/{y}.png?key='.$maptilerKey
                        : 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
                ),
                'attribution' => env(
                    'MAP_CLEAN_ATTRIBUTION',
                    $useMaptiler
                        ? $maptilerAttribution
                        : 'Map data © OpenStreetMap contributors | Map style © CARTO',
                ),
                'max_zoom' => (int) env('MAP_CLEAN_MAX_ZOOM', 20),
            ],
            'activity' => [
                'label' => 'Activity',
                'url' => env(
                    'MAP_ACTIVITY_TILE_URL',
                    $useMaptiler
                        ? 'https://api.maptiler.com/maps/outdoor-v2/256/{z}/{x}/{y}.png?key='.$maptilerKey
                        : 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                ),
                'attribution' => env(
                    'MAP_ACTIVITY_ATTRIBUTION',
                    $useMaptiler
                        ? $maptilerAttribution
                        : 'Map data © OpenStreetMap contributors',
                ),
                'max_zoom' => (int) env('MAP_ACTIVITY_MAX_ZOOM', 19),
            ],
        ],
    ],

    'posthog' => [
        'enabled' => (bool) env('POSTHOG_ENABLED', false),
        'key' => env('POSTHOG_KEY'),
        'host' => env('POSTHOG_HOST', 'https://eu.i.posthog.com'),
    ],

    'sentry' => [
        'dsn' => env('SENTRY_LARAVEL_DSN'),
        'frontend_dsn' => env('VITE_SENTRY_DSN'),
        'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),
    ],

    'better_stack' => [
        'heartbeat_url' => env('BETTER_STACK_HEARTBEAT_URL'),
        'uptime_monitor_url' => env('BETTER_STACK_UPTIME_MONITOR_URL'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
