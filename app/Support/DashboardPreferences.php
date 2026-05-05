<?php

namespace App\Support;

class DashboardPreferences
{
    /**
     * @return array<int, string>
     */
    public static function sectionIds(): array
    {
        return [
            'headline',
            'sea-state',
            'route-map',
            'expeditions',
        ];
    }

    /**
     * @return array{order: array<int, string>, hidden: array<int, string>}
     */
    public static function defaults(): array
    {
        return [
            'order' => self::sectionIds(),
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
        $allowed = self::sectionIds();

        if (! is_array($value)) {
            return $defaults;
        }

        $rawOrder = is_array($value['order'] ?? null) ? $value['order'] : [];
        $rawHidden = is_array($value['hidden'] ?? null) ? $value['hidden'] : [];

        $order = collect($rawOrder)
            ->map(fn ($item) => is_string($item) ? $item : null)
            ->filter(fn (?string $item) => $item !== null && in_array($item, $allowed, true))
            ->unique()
            ->values();

        foreach ($allowed as $sectionId) {
            if (! $order->contains($sectionId)) {
                $order->push($sectionId);
            }
        }

        $hidden = collect($rawHidden)
            ->map(fn ($item) => is_string($item) ? $item : null)
            ->filter(fn (?string $item) => $item !== null && in_array($item, $allowed, true))
            ->unique()
            ->values()
            ->all();

        return [
            'order' => $order->all(),
            'hidden' => $hidden,
        ];
    }
}
