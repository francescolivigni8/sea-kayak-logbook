<?php

namespace App\Support;

class DashboardPreferences
{
    /**
     * @return array<int, string>
     */
    public static function cardIds(): array
    {
        return [
            'metric-distance',
            'metric-duration',
            'metric-air-temperature',
            'metric-sea-temperature',
            'metric-average-speed',
            'sea-beaufort-distribution',
            'sea-wind-counts',
            'sea-rescue-events',
            'sea-profile',
            'sea-environmental-conditions',
            'sea-distance-by-month',
            'sea-timeframe-comparison',
            'route-map',
            'expedition-distance',
            'expedition-days',
            'expedition-trips',
            'expedition-map',
            'expedition-sessions',
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function legacySectionMap(): array
    {
        return [
            'headline' => [
                'metric-distance',
                'metric-duration',
                'metric-air-temperature',
                'metric-sea-temperature',
                'metric-average-speed',
            ],
            'sea-state' => [
                'sea-beaufort-distribution',
                'sea-wind-counts',
                'sea-rescue-events',
                'sea-profile',
                'sea-environmental-conditions',
                'sea-distance-by-month',
                'sea-timeframe-comparison',
            ],
            'route-map' => ['route-map'],
            'expeditions' => [
                'expedition-distance',
                'expedition-days',
                'expedition-trips',
                'expedition-map',
                'expedition-sessions',
            ],
        ];
    }

    /**
     * @return array{order: array<int, string>, hidden: array<int, string>}
     */
    public static function defaults(): array
    {
        return [
            'order' => self::cardIds(),
            'hidden' => [],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $settings
     * @return array{order: array<int, string>, hidden: array<int, string>}
     */
    public static function fromSettings(?array $settings): array
    {
        return self::sanitize(data_get($settings, 'dashboard_layout'));
    }

    /**
     * @return array{order: array<int, string>, hidden: array<int, string>}
     */
    public static function sanitize(mixed $value): array
    {
        $defaults = self::defaults();
        $allowed = self::cardIds();
        $legacy = self::legacySectionMap();

        if (! is_array($value)) {
            return $defaults;
        }

        $rawOrder = is_array($value['order'] ?? null) ? $value['order'] : [];
        $rawHidden = is_array($value['hidden'] ?? null) ? $value['hidden'] : [];

        $order = collect($rawOrder)
            ->flatMap(function ($item) use ($allowed, $legacy) {
                if (! is_string($item)) {
                    return [];
                }

                if (array_key_exists($item, $legacy)) {
                    return $legacy[$item];
                }

                return in_array($item, $allowed, true) ? [$item] : [];
            })
            ->unique()
            ->values();

        foreach ($allowed as $cardId) {
            if (! $order->contains($cardId)) {
                $order->push($cardId);
            }
        }

        $hidden = collect($rawHidden)
            ->flatMap(function ($item) use ($allowed, $legacy) {
                if (! is_string($item)) {
                    return [];
                }

                if (array_key_exists($item, $legacy)) {
                    return $legacy[$item];
                }

                return in_array($item, $allowed, true) ? [$item] : [];
            })
            ->unique()
            ->values()
            ->all();

        return [
            'order' => $order->all(),
            'hidden' => $hidden,
        ];
    }
}
