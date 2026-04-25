<?php

namespace App\Support;

class UnitPreferences
{
    public const DISTANCE_UNITS = ['km', 'nm'];

    public const SPEED_UNITS = ['kmh', 'kt'];

    public const WIND_UNITS = ['ms', 'kmh', 'kt'];

    public const CURRENT_UNITS = ['kt', 'kmh', 'ms'];

    public const TEMPERATURE_UNITS = ['c', 'f'];

    /**
     * @return array{distance:string,speed:string,wind:string,current:string,temperature:string}
     */
    public static function defaults(): array
    {
        return [
            'distance' => 'km',
            'speed' => 'kmh',
            'wind' => 'kmh',
            'current' => 'kmh',
            'temperature' => 'c',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $settings
     * @return array{distance:string,speed:string,wind:string,current:string,temperature:string}
     */
    public static function fromSettings(?array $settings): array
    {
        $fallback = self::legacyDefaults($settings);
        $stored = data_get($settings, 'unit_preferences');

        if (! is_array($stored)) {
            return $fallback;
        }

        return self::sanitize($stored, $fallback);
    }

    /**
     * @param  array<string, mixed>  $preferences
     * @param  array{distance:string,speed:string,wind:string,current:string,temperature:string}|null  $fallback
     * @return array{distance:string,speed:string,wind:string,current:string,temperature:string}
     */
    public static function sanitize(array $preferences, ?array $fallback = null): array
    {
        $resolvedFallback = $fallback ?? self::defaults();

        return [
            'distance' => self::valueOrFallback(
                $preferences['distance'] ?? null,
                self::DISTANCE_UNITS,
                $resolvedFallback['distance'],
            ),
            'speed' => self::valueOrFallback(
                $preferences['speed'] ?? null,
                self::SPEED_UNITS,
                $resolvedFallback['speed'],
            ),
            'wind' => self::valueOrFallback(
                $preferences['wind'] ?? null,
                self::WIND_UNITS,
                $resolvedFallback['wind'],
            ),
            'current' => self::valueOrFallback(
                $preferences['current'] ?? null,
                self::CURRENT_UNITS,
                $resolvedFallback['current'],
            ),
            'temperature' => self::valueOrFallback(
                $preferences['temperature'] ?? null,
                self::TEMPERATURE_UNITS,
                $resolvedFallback['temperature'],
            ),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $settings
     * @return array{distance:string,speed:string,wind:string,current:string,temperature:string}
     */
    public static function legacyDefaults(?array $settings): array
    {
        $legacy = data_get($settings, 'planning_unit_system');

        if ($legacy === 'marine') {
            return [
                'distance' => 'nm',
                'speed' => 'kt',
                'wind' => 'kt',
                'current' => 'kt',
                'temperature' => 'c',
            ];
        }

        return self::defaults();
    }

    /**
     * @param  array{distance:string,speed:string,wind:string,current:string,temperature:string}  $preferences
     */
    public static function legacyPreset(array $preferences): string
    {
        return $preferences['distance'] === 'nm' && $preferences['speed'] === 'kt'
            ? 'marine'
            : 'metric';
    }

    /**
     * @param  string[]  $allowed
     */
    private static function valueOrFallback(mixed $value, array $allowed, string $fallback): string
    {
        return is_string($value) && in_array($value, $allowed, true)
            ? $value
            : $fallback;
    }
}
