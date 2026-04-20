<?php

namespace App\Support;

use App\Models\PaddleSession;

class PlanningForecastService
{
    private const MARINE_FALLBACK_FIELDS = [
        'current_knots',
        'current_direction_deg',
        'wave_height_m',
        'swell_height_m',
        'swell_period_s',
        'swell_direction_deg',
        'sea_temp_c',
        'tide_state',
    ];

    public function __construct(
        private readonly StormglassWeatherService $stormglass,
        private readonly OpenMeteoMarineWeatherService $openMeteo,
    ) {}

    public function isConfigured(): bool
    {
        return $this->stormglass->isConfigured() || $this->openMeteo->isConfigured();
    }

    public function previewForecastBoard(PaddleSession $session): array
    {
        $stormglassResult = null;

        if ($this->stormglass->isConfigured()) {
            $stormglassResult = $this->withProvider(
                $this->stormglass->previewForecastBoard($session),
                'stormglass',
            );

            if (($stormglassResult['status'] ?? null) === 'filled') {
                if ($this->openMeteo->isConfigured() && $this->needsMarineFallback($stormglassResult)) {
                    $openMeteoResult = $this->withProvider(
                        $this->openMeteo->previewForecastBoard($session),
                        'open_meteo',
                    );

                    if (($openMeteoResult['status'] ?? null) === 'filled') {
                        return $this->mergeMissingMarineFields($stormglassResult, $openMeteoResult);
                    }
                }

                return $stormglassResult;
            }
        }

        if ($this->openMeteo->isConfigured()) {
            $openMeteoResult = $this->withProvider(
                $this->openMeteo->previewForecastBoard($session),
                'open_meteo',
            );

            if ($stormglassResult !== null && ($openMeteoResult['status'] ?? null) === 'filled') {
                $openMeteoResult['fallbackFrom'] = [
                    'provider' => 'stormglass',
                    'status' => $stormglassResult['status'] ?? 'unknown',
                    'reason' => $stormglassResult['reason'] ?? null,
                    'httpStatus' => $stormglassResult['httpStatus'] ?? null,
                    'providerMessage' => $stormglassResult['providerMessage'] ?? null,
                ];
            }

            return $openMeteoResult;
        }

        return $stormglassResult ?? [
            'status' => 'skipped',
            'provider' => null,
            'reason' => 'No planning forecast provider is configured yet.',
            'filledFields' => 0,
            'fields' => [],
            'timeline' => [],
        ];
    }

    private function withProvider(array $result, string $provider): array
    {
        return [
            'provider' => $provider,
            ...$result,
        ];
    }

    private function needsMarineFallback(array $result): bool
    {
        $fields = $result['fields'] ?? [];

        if (! is_array($fields)) {
            return true;
        }

        foreach (self::MARINE_FALLBACK_FIELDS as $field) {
            if (! $this->hasValue($fields[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function mergeMissingMarineFields(array $primary, array $fallback): array
    {
        $primary['fields'] = $this->mergeMarineFields(
            is_array($primary['fields'] ?? null) ? $primary['fields'] : [],
            is_array($fallback['fields'] ?? null) ? $fallback['fields'] : [],
        );
        $primary['timeline'] = $this->mergeMarineTimeline(
            is_array($primary['timeline'] ?? null) ? $primary['timeline'] : [],
            is_array($fallback['timeline'] ?? null) ? $fallback['timeline'] : [],
        );
        $primary['filledFields'] = $this->countFilledFields($primary['fields']);
        $primary['marineFallback'] = [
            'provider' => $fallback['provider'] ?? 'open_meteo',
            'status' => $fallback['status'] ?? null,
        ];

        return $primary;
    }

    private function mergeMarineTimeline(array $primaryTimeline, array $fallbackTimeline): array
    {
        if ($primaryTimeline === []) {
            return $fallbackTimeline;
        }

        $fallbackByTime = collect($fallbackTimeline)
            ->filter(fn (mixed $slot) => is_array($slot) && isset($slot['time']))
            ->keyBy('time');

        return collect($primaryTimeline)
            ->map(function (mixed $slot) use ($fallbackByTime) {
                if (! is_array($slot) || ! isset($slot['time'])) {
                    return $slot;
                }

                $fallbackSlot = $fallbackByTime->get($slot['time']);

                if (! is_array($fallbackSlot)) {
                    return $slot;
                }

                $slot['fields'] = $this->mergeMarineFields(
                    is_array($slot['fields'] ?? null) ? $slot['fields'] : [],
                    is_array($fallbackSlot['fields'] ?? null) ? $fallbackSlot['fields'] : [],
                );
                $slot['filledFields'] = $this->countFilledFields($slot['fields']);
                $slot['status'] = $slot['filledFields'] > 0 ? 'filled' : ($slot['status'] ?? 'skipped');

                return $slot;
            })
            ->values()
            ->all();
    }

    private function mergeMarineFields(array $primaryFields, array $fallbackFields): array
    {
        foreach (self::MARINE_FALLBACK_FIELDS as $field) {
            if (! $this->hasValue($primaryFields[$field] ?? null) && $this->hasValue($fallbackFields[$field] ?? null)) {
                $primaryFields[$field] = $fallbackFields[$field];
            }
        }

        if (! $this->hasValue($primaryFields['forecast_severity'] ?? null) && $this->hasValue($fallbackFields['forecast_severity'] ?? null)) {
            $primaryFields['forecast_severity'] = $fallbackFields['forecast_severity'];
        }

        return $primaryFields;
    }

    private function countFilledFields(array $fields): int
    {
        return collect($fields)
            ->filter(fn (mixed $value) => $this->hasValue($value))
            ->count();
    }

    private function hasValue(mixed $value): bool
    {
        return $value !== null && $value !== '';
    }
}
