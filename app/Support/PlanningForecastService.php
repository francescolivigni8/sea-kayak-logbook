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

    private const SEVERITY_ORDER = [
        'low' => 1,
        'moderate' => 2,
        'high' => 3,
        'extreme' => 4,
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
        $openMeteoResult = null;

        if ($this->stormglass->isConfigured()) {
            $stormglassResult = $this->withProvider(
                $this->stormglass->previewForecastBoard($session),
                'stormglass',
            );
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
        }

        if (($stormglassResult['status'] ?? null) === 'filled') {
            $result = $stormglassResult;

            if (($openMeteoResult['status'] ?? null) === 'filled' && $this->needsMarineFallback($stormglassResult)) {
                $result = $this->mergeMissingMarineFields($stormglassResult, $openMeteoResult);
            }

            return $this->applyPlanningTrustAdjustments($result, $openMeteoResult);
        }

        if (($openMeteoResult['status'] ?? null) === 'filled') {
            return $this->applyPlanningTrustAdjustments($openMeteoResult, null);
        }

        return $this->applyPlanningTrustAdjustments($stormglassResult ?? $openMeteoResult ?? [
            'status' => 'skipped',
            'provider' => null,
            'reason' => 'No planning forecast provider is configured yet.',
            'filledFields' => 0,
            'fields' => [],
            'timeline' => [],
        ], null);
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

    private function applyPlanningTrustAdjustments(array $result, ?array $secondaryComparison): array
    {
        $fields = is_array($result['fields'] ?? null) ? $result['fields'] : [];
        $secondaryFields = is_array($secondaryComparison['fields'] ?? null) ? $secondaryComparison['fields'] : [];
        $windValidation = $this->buildWindValidation(
            $fields,
            $secondaryFields,
            $result['provider'] ?? null,
            $secondaryComparison['provider'] ?? null,
        );

        if ($fields !== []) {
            $result['fields'] = $this->normalizePlanningFields($fields, $windValidation['status'] ?? 'single-source');
            $result['filledFields'] = $this->countFilledFields($result['fields']);
        }

        $result['windValidation'] = $windValidation;

        return $result;
    }

    private function buildWindValidation(
        array $primaryFields,
        array $secondaryFields,
        ?string $primaryProvider,
        ?string $secondaryProvider,
    ): array {
        $primaryWind = $this->numericOrNull($primaryFields['wind_avg_ms'] ?? null);
        $primaryGust = $this->numericOrNull($primaryFields['wind_gust_ms'] ?? null);
        $secondaryWind = $this->numericOrNull($secondaryFields['wind_avg_ms'] ?? null);
        $secondaryGust = $this->numericOrNull($secondaryFields['wind_gust_ms'] ?? null);
        $avgDelta = $primaryWind !== null && $secondaryWind !== null
            ? round(abs($primaryWind - $secondaryWind), 1)
            : null;
        $gustDelta = $primaryGust !== null && $secondaryGust !== null
            ? round(abs($primaryGust - $secondaryGust), 1)
            : null;
        $primarySpread = $primaryWind !== null && $primaryGust !== null
            ? round(max($primaryGust - $primaryWind, 0), 1)
            : null;
        $secondarySpread = $secondaryWind !== null && $secondaryGust !== null
            ? round(max($secondaryGust - $secondaryWind, 0), 1)
            : null;
        $status = 'single-source';
        $suppressesGustEscalation = false;

        if (
            $primaryWind !== null &&
            $secondaryWind !== null &&
            $primaryGust !== null &&
            $secondaryGust !== null &&
            $secondaryProvider !== null
        ) {
            $suspiciousGustSpike = ($gustDelta ?? 0) >= 6.0
                && ($avgDelta ?? 0) <= 3.0
                && (
                    ($primarySpread !== null && $primarySpread >= 8.0) ||
                    ($secondarySpread !== null && $secondarySpread >= 8.0) ||
                    (
                        $primarySpread !== null &&
                        $secondarySpread !== null &&
                        abs($primarySpread - $secondarySpread) >= 6.0
                    )
                );

            if ($suspiciousGustSpike) {
                $status = 'uncertain';
                $suppressesGustEscalation = true;
            } elseif (($avgDelta ?? 0) <= 2.5 && ($gustDelta ?? 0) <= 4.0) {
                $status = 'aligned';
            } else {
                $status = 'watch';
            }
        }

        return [
            'status' => $status,
            'primaryProvider' => $primaryProvider,
            'secondaryProvider' => $secondaryProvider,
            'primaryWindAvgMs' => $primaryWind,
            'primaryWindGustMs' => $primaryGust,
            'secondaryWindAvgMs' => $secondaryWind,
            'secondaryWindGustMs' => $secondaryGust,
            'avgDeltaMs' => $avgDelta,
            'gustDeltaMs' => $gustDelta,
            'suppressesGustEscalation' => $suppressesGustEscalation,
        ];
    }

    private function normalizePlanningFields(array $fields, string $windValidationStatus): array
    {
        $windSeverity = $this->resolvePlanningWindSeverity(
            $this->numericOrNull($fields['wind_avg_ms'] ?? null),
            $this->numericOrNull($fields['wind_gust_ms'] ?? null),
            $this->intOrNull($fields['wind_beaufort'] ?? null),
            $windValidationStatus,
        );

        if ($windSeverity !== null) {
            $fields['wind_severity'] = $windSeverity;
        }

        $forecastSeverity = $this->resolvePlanningForecastSeverity($fields, $windSeverity);

        if ($forecastSeverity !== null) {
            $fields['forecast_severity'] = $forecastSeverity;
        }

        return $fields;
    }

    private function resolvePlanningWindSeverity(
        ?float $windSpeed,
        ?float $windGust,
        ?int $beaufort,
        string $windValidationStatus,
    ): ?string {
        $baseSeverity = $this->baseWindSeverity($windSpeed, $beaufort);
        $gustSeverity = $this->gustSeverity($windGust);

        if ($baseSeverity === null) {
            return $gustSeverity;
        }

        if ($gustSeverity === null) {
            return $baseSeverity;
        }

        if ($windValidationStatus === 'uncertain') {
            return $baseSeverity;
        }

        return $this->maxSeverity(
            $baseSeverity,
            $this->minSeverity($gustSeverity, $this->raiseSeverity($baseSeverity, 1)),
        );
    }

    private function resolvePlanningForecastSeverity(array $fields, ?string $windSeverity): ?string
    {
        $severity = $this->maxSeverity(
            $windSeverity,
            $this->stringOrNull($fields['rain_severity'] ?? null),
            $this->stringOrNull($fields['temperature_severity'] ?? null),
        );

        return $this->maxSeverity(
            $severity,
            match (true) {
                $this->numericOrNull($fields['wave_height_m'] ?? null) !== null
                    && $this->numericOrNull($fields['wave_height_m'] ?? null) >= 2.0 => 'extreme',
                $this->numericOrNull($fields['swell_height_m'] ?? null) !== null
                    && $this->numericOrNull($fields['swell_height_m'] ?? null) >= 2.5 => 'extreme',
                $this->numericOrNull($fields['current_knots'] ?? null) !== null
                    && $this->numericOrNull($fields['current_knots'] ?? null) >= 3.0 => 'extreme',
                $this->stringOrNull($fields['visibility_code'] ?? null) === 'fog' => 'extreme',
                $this->numericOrNull($fields['wave_height_m'] ?? null) !== null
                    && $this->numericOrNull($fields['wave_height_m'] ?? null) >= 1.2 => 'high',
                $this->numericOrNull($fields['swell_height_m'] ?? null) !== null
                    && $this->numericOrNull($fields['swell_height_m'] ?? null) >= 1.5 => 'high',
                $this->numericOrNull($fields['current_knots'] ?? null) !== null
                    && $this->numericOrNull($fields['current_knots'] ?? null) >= 2.0 => 'high',
                $this->stringOrNull($fields['visibility_code'] ?? null) === 'poor' => 'high',
                $this->numericOrNull($fields['wave_height_m'] ?? null) !== null
                    && $this->numericOrNull($fields['wave_height_m'] ?? null) >= 0.6 => 'moderate',
                $this->numericOrNull($fields['swell_height_m'] ?? null) !== null
                    && $this->numericOrNull($fields['swell_height_m'] ?? null) >= 0.8 => 'moderate',
                $this->numericOrNull($fields['current_knots'] ?? null) !== null
                    && $this->numericOrNull($fields['current_knots'] ?? null) >= 1.0 => 'moderate',
                in_array($this->stringOrNull($fields['visibility_code'] ?? null), ['moderate', 'good', 'clear'], true) => 'low',
                default => null,
            },
        );
    }

    private function baseWindSeverity(?float $windSpeed, ?int $beaufort): ?string
    {
        if ($windSpeed === null && $beaufort === null) {
            return null;
        }

        return match (true) {
            ($windSpeed !== null && $windSpeed >= 17.2) || ($beaufort !== null && $beaufort >= 8) => 'extreme',
            ($windSpeed !== null && $windSpeed >= 10.8) || ($beaufort !== null && $beaufort >= 6) => 'high',
            ($windSpeed !== null && $windSpeed >= 5.5) || ($beaufort !== null && $beaufort >= 4) => 'moderate',
            default => 'low',
        };
    }

    private function gustSeverity(?float $windGust): ?string
    {
        if ($windGust === null) {
            return null;
        }

        return match (true) {
            $windGust >= 20.8 => 'extreme',
            $windGust >= 13.9 => 'high',
            $windGust >= 8.0 => 'moderate',
            default => 'low',
        };
    }

    private function raiseSeverity(string $severity, int $steps): string
    {
        $rank = min((self::SEVERITY_ORDER[$severity] ?? 1) + $steps, max(self::SEVERITY_ORDER));

        return array_search($rank, self::SEVERITY_ORDER, true) ?: $severity;
    }

    private function maxSeverity(?string ...$values): ?string
    {
        return collect($values)
            ->filter(fn (?string $value) => $value !== null && isset(self::SEVERITY_ORDER[$value]))
            ->sortBy(fn (string $value) => self::SEVERITY_ORDER[$value])
            ->last();
    }

    private function minSeverity(?string $left, ?string $right): ?string
    {
        if ($left === null) {
            return $right;
        }

        if ($right === null) {
            return $left;
        }

        return (self::SEVERITY_ORDER[$left] ?? 0) <= (self::SEVERITY_ORDER[$right] ?? 0)
            ? $left
            : $right;
    }

    private function numericOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) round((float) $value) : null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
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
