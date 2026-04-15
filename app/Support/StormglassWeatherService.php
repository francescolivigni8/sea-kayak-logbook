<?php

namespace App\Support;

use App\Models\PaddleSession;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

class StormglassWeatherService
{
    public function isConfigured(): bool
    {
        return filled($this->apiKey());
    }

    public function enrichSession(PaddleSession $session): array
    {
        if (! $this->isConfigured()) {
            return [
                'status' => 'skipped',
                'reason' => 'Stormglass API key is not configured yet.',
                'filledFields' => 0,
            ];
        }

        [$lat, $lng] = $this->coordinatesFor($session);

        if ($lat === null || $lng === null) {
            return [
                'status' => 'skipped',
                'reason' => 'No saved launch or landing coordinates were available.',
                'filledFields' => 0,
            ];
        }

        $targetTime = $this->targetTime($session);

        try {
            $hour = $this->fetchNearestForecastHour($lat, $lng, $targetTime);
        } catch (Throwable $exception) {
            report($exception);

            return [
                'status' => 'failed',
                'reason' => 'Stormglass request failed.',
                'filledFields' => 0,
            ];
        }

        if (! $hour) {
            return [
                'status' => 'skipped',
                'reason' => 'Stormglass returned no usable forecast hour.',
                'filledFields' => 0,
            ];
        }

        $filledFields = $this->applyForecast($session, $hour);

        if ($filledFields === 0) {
            return [
                'status' => 'skipped',
                'reason' => 'Stormglass did not return any values we could apply.',
                'filledFields' => 0,
            ];
        }

        $session->conditions_logged = true;
        $session->save();

        return [
            'status' => 'filled',
            'reason' => null,
            'filledFields' => $filledFields,
        ];
    }

    public function enrichSessions(iterable $sessions): array
    {
        $summary = [
            'filled' => 0,
            'skipped' => 0,
            'failed' => 0,
            'filledFields' => 0,
        ];

        foreach ($sessions as $session) {
            $result = $this->enrichSession($session);

            match ($result['status']) {
                'filled' => $summary['filled']++,
                'failed' => $summary['failed']++,
                default => $summary['skipped']++,
            };

            $summary['filledFields'] += (int) ($result['filledFields'] ?? 0);
        }

        return $summary;
    }

    private function fetchNearestForecastHour(float $lat, float $lng, CarbonInterface $targetTime): ?array
    {
        $response = Http::timeout($this->timeout())
            ->withHeaders([
                $this->authHeader() => $this->apiKey(),
            ])
            ->get($this->baseUrl(), [
                'lat' => $lat,
                'lng' => $lng,
                'params' => implode(',', $this->params()),
                'source' => $this->source(),
            ])
            ->throw();

        $hours = $response->json('hours');

        if (! is_array($hours) || $hours === []) {
            return null;
        }

        $targetTimestamp = Carbon::instance($targetTime)->utc()->timestamp;

        return collect($hours)
            ->map(function ($hour) use ($targetTimestamp) {
                if (! is_array($hour) || empty($hour['time'])) {
                    return null;
                }

                $timestamp = Carbon::parse((string) $hour['time'])->timestamp;

                return [
                    'hour' => $hour,
                    'distance' => abs($timestamp - $targetTimestamp),
                ];
            })
            ->filter()
            ->sortBy('distance')
            ->pluck('hour')
            ->first();
    }

    private function applyForecast(PaddleSession $session, array $hour): int
    {
        $filled = 0;

        $windSpeed = $this->extractNumericValue($hour, 'windSpeed');
        $windGust = $this->extractNumericValue($hour, 'gust');
        $windDirection = $this->extractNumericValue($hour, 'windDirection');
        $currentSpeed = $this->extractNumericValue($hour, 'currentSpeed');
        $currentDirection = $this->extractNumericValue($hour, 'currentDirection');
        $waveHeight = $this->extractNumericValue($hour, 'waveHeight');
        $swellHeight = $this->extractNumericValue($hour, 'swellHeight');
        $swellPeriod = $this->extractNumericValue($hour, 'swellPeriod');
        $swellDirection = $this->extractNumericValue($hour, 'swellDirection');
        $airTemperature = $this->extractNumericValue($hour, 'airTemperature');
        $waterTemperature = $this->extractNumericValue($hour, 'waterTemperature');
        $visibility = $this->extractNumericValue($hour, 'visibility');

        $filled += $this->assignRounded($session, 'wind_avg_ms', $windSpeed, 1);
        $filled += $this->assignRounded($session, 'wind_gust_ms', $windGust, 1);
        $filled += $this->assignInt($session, 'wind_direction_deg', $windDirection);
        $filled += $this->assignRounded($session, 'current_knots', $currentSpeed !== null ? $currentSpeed * 1.943844 : null, 1);
        $filled += $this->assignInt($session, 'current_direction_deg', $currentDirection);
        $filled += $this->assignRounded($session, 'wave_height_m', $waveHeight, 1);
        $filled += $this->assignRounded($session, 'swell_height_m', $swellHeight, 1);
        $filled += $this->assignRounded($session, 'swell_period_s', $swellPeriod, 1);
        $filled += $this->assignInt($session, 'swell_direction_deg', $swellDirection);
        $filled += $this->assignRounded($session, 'air_temp_c', $airTemperature, 1);
        $filled += $this->assignRounded($session, 'sea_temp_c', $waterTemperature, 1);

        $visibilityCode = $this->mapVisibilityCode($visibility);
        if ($visibilityCode !== null) {
            $session->visibility_code = $visibilityCode;
            $filled++;
        }

        $beaufort = $this->resolveBeaufort($windSpeed);
        if ($beaufort !== null) {
            $session->wind_beaufort = $beaufort;
            $filled++;
        }

        $summary = $this->buildSummary($session);
        if ($summary !== null) {
            $session->weather_summary = $summary;
            $filled++;
        }

        return $filled;
    }

    private function buildSummary(PaddleSession $session): ?string
    {
        $parts = array_filter([
            $session->wind_beaufort !== null ? sprintf('F%d / %.1f m/s wind', $session->wind_beaufort, (float) $session->wind_avg_ms) : null,
            $session->wind_gust_ms !== null ? sprintf('gust %.1f m/s', (float) $session->wind_gust_ms) : null,
            $session->swell_height_m !== null
                ? sprintf('swell %.1f m @ %.1f s', (float) $session->swell_height_m, (float) ($session->swell_period_s ?? 0))
                : ($session->wave_height_m !== null ? sprintf('wave %.1f m', (float) $session->wave_height_m) : null),
            $session->current_knots !== null ? sprintf('current %.1f kt', (float) $session->current_knots) : null,
            $session->air_temp_c !== null ? sprintf('air %.1f C', (float) $session->air_temp_c) : null,
            $session->sea_temp_c !== null ? sprintf('sea %.1f C', (float) $session->sea_temp_c) : null,
            $session->visibility_code ? sprintf('visibility %s', $session->visibility_code) : null,
        ]);

        if ($parts === []) {
            return null;
        }

        return implode(', ', $parts).'.';
    }

    private function assignRounded(PaddleSession $session, string $field, ?float $value, int $precision): int
    {
        if ($value === null) {
            return 0;
        }

        $session->{$field} = round($value, $precision);

        return 1;
    }

    private function assignInt(PaddleSession $session, string $field, ?float $value): int
    {
        if ($value === null) {
            return 0;
        }

        $session->{$field} = (int) round($value);

        return 1;
    }

    private function extractNumericValue(array $hour, string $parameter): ?float
    {
        $value = $hour[$parameter] ?? null;

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (! is_array($value)) {
            return null;
        }

        $preferredKeys = array_unique(array_filter([
            $this->source(),
            strtolower($this->source()),
            explode(':', $this->source())[0] ?? null,
            'sg',
        ]));

        foreach ($preferredKeys as $key) {
            if (isset($value[$key]) && is_numeric($value[$key])) {
                return (float) $value[$key];
            }
        }

        foreach ($value as $candidate) {
            if (is_numeric($candidate)) {
                return (float) $candidate;
            }
        }

        return null;
    }

    private function mapVisibilityCode(?float $visibility): ?string
    {
        if ($visibility === null) {
            return null;
        }

        if ($visibility > 100) {
            return match (true) {
                $visibility >= 10000 => 'clear',
                $visibility >= 5000 => 'good',
                $visibility >= 2000 => 'moderate',
                $visibility >= 500 => 'poor',
                default => 'fog',
            };
        }

        return match (true) {
            $visibility >= 10 => 'clear',
            $visibility >= 5 => 'good',
            $visibility >= 2 => 'moderate',
            $visibility >= 0.5 => 'poor',
            default => 'fog',
        };
    }

    private function resolveBeaufort(?float $windSpeed): ?int
    {
        if ($windSpeed === null) {
            return null;
        }

        return match (true) {
            $windSpeed < 0.3 => 0,
            $windSpeed < 1.6 => 1,
            $windSpeed < 3.4 => 2,
            $windSpeed < 5.5 => 3,
            $windSpeed < 8.0 => 4,
            $windSpeed < 10.8 => 5,
            $windSpeed < 13.9 => 6,
            $windSpeed < 17.2 => 7,
            $windSpeed < 20.8 => 8,
            $windSpeed < 24.5 => 9,
            $windSpeed < 28.5 => 10,
            $windSpeed < 32.7 => 11,
            default => 12,
        };
    }

    private function coordinatesFor(PaddleSession $session): array
    {
        if ($session->launch_lat !== null && $session->launch_lng !== null) {
            return [(float) $session->launch_lat, (float) $session->launch_lng];
        }

        if ($session->landing_lat !== null && $session->landing_lng !== null) {
            return [(float) $session->landing_lat, (float) $session->landing_lng];
        }

        return [null, null];
    }

    private function targetTime(PaddleSession $session): CarbonInterface
    {
        if ($session->start_at !== null) {
            return $session->start_at;
        }

        return Carbon::parse($session->session_date?->toDateString() ?? now()->toDateString(), $session->timezone ?: 'UTC')
            ->setTime(12, 0, 0)
            ->utc();
    }

    private function apiKey(): ?string
    {
        return config('kayak.weather.providers.stormglass.api_key');
    }

    private function baseUrl(): string
    {
        return config('kayak.weather.providers.stormglass.base_url');
    }

    private function authHeader(): string
    {
        return config('kayak.weather.providers.stormglass.auth_header', 'Authorization');
    }

    private function source(): string
    {
        return config('kayak.weather.providers.stormglass.source', 'sg');
    }

    private function timeout(): int
    {
        return (int) config('kayak.weather.providers.stormglass.timeout', 10);
    }

    /**
     * @return array<int, string>
     */
    private function params(): array
    {
        return config('kayak.weather.providers.stormglass.params', []);
    }
}
