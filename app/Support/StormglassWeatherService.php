<?php

namespace App\Support;

use App\Models\PaddleSession;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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
        $result = $this->previewSession($session);

        if ($result['status'] !== 'filled') {
            return $result;
        }

        $session->conditions_logged = true;
        $session->save();

        return $result;
    }

    public function previewSession(PaddleSession $session): array
    {
        if (! $this->isConfigured()) {
            return [
                'status' => 'skipped',
                'reason' => 'Stormglass API key is not configured yet.',
                'filledFields' => 0,
                'fields' => [],
            ];
        }

        [$lat, $lng] = $this->coordinatesFor($session);

        if ($lat === null || $lng === null) {
            return [
                'status' => 'skipped',
                'reason' => 'No saved launch or landing coordinates were available.',
                'filledFields' => 0,
                'fields' => [],
            ];
        }

        $targetTime = $this->targetTime($session);

        try {
            $hour = $this->fetchNearestForecastHour($lat, $lng, $targetTime);
        } catch (RequestException $exception) {
            report($exception);

            return [
                'status' => 'failed',
                'reason' => $this->requestFailureMessage($exception),
                'httpStatus' => $exception->response?->status(),
                'filledFields' => 0,
                'fields' => [],
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'status' => 'failed',
                'reason' => 'Stormglass request failed.',
                'filledFields' => 0,
                'fields' => [],
            ];
        }

        if (! $hour) {
            return [
                'status' => 'skipped',
                'reason' => 'Stormglass returned no usable forecast hour.',
                'filledFields' => 0,
                'fields' => [],
            ];
        }

        $filledFields = $this->applyForecast($session, $hour);

        if ($filledFields === 0) {
            return [
                'status' => 'skipped',
                'reason' => 'Stormglass did not return any values we could apply.',
                'filledFields' => 0,
                'fields' => [],
            ];
        }

        return [
            'status' => 'filled',
            'reason' => null,
            'filledFields' => $filledFields,
            'fields' => $this->extractFields($session),
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
        $hours = Cache::remember(
            $this->cacheKey('weather', $lat, $lng, $targetTime, implode(',', $this->params())),
            $this->cacheSeconds(),
            function () use ($lat, $lng, $targetTime) {
                $response = Http::timeout($this->timeout())
                    ->withHeaders([
                        $this->authHeader() => $this->apiKey(),
                    ])
                    ->get($this->baseUrl(), [
                        'lat' => $lat,
                        'lng' => $lng,
                        'start' => Carbon::instance($targetTime)->copy()->subHours(6)->utc()->toIso8601String(),
                        'end' => Carbon::instance($targetTime)->copy()->addHours(6)->utc()->toIso8601String(),
                        'params' => implode(',', $this->params()),
                        'source' => $this->source(),
                    ])
                    ->throw();

                $hours = $response->json('hours');

                return is_array($hours) ? $hours : [];
            },
        );

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

    private function fetchTideExtremes(float $lat, float $lng, CarbonInterface $targetTime): array
    {
        $cacheKey = $this->cacheKey('tide', $lat, $lng, $targetTime, 'extremes');
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $start = Carbon::instance($targetTime)->copy()->subHours(12)->utc()->toIso8601String();
        $end = Carbon::instance($targetTime)->copy()->addHours(12)->utc()->toIso8601String();

        $response = Http::timeout($this->timeout())
            ->withHeaders([
                $this->authHeader() => $this->apiKey(),
            ])
            ->get($this->tideExtremesUrl(), [
                'lat' => $lat,
                'lng' => $lng,
                'start' => $start,
                'end' => $end,
            ]);

        if (! $response->successful()) {
            return [];
        }

        $data = $response->json('data');
        $extremes = is_array($data) ? $data : [];

        if ($this->cacheSeconds() > 0) {
            Cache::put($cacheKey, $extremes, $this->cacheSeconds());
        }

        return $extremes;
    }

    private function applyForecast(PaddleSession $session, array $hour): int
    {
        $filled = 0;
        [$lat, $lng] = $this->coordinatesFor($session);
        $targetTime = $this->targetTime($session);

        $windSpeed = $this->extractNumericValue($hour, 'windSpeed');
        $windGust = $this->extractNumericValue($hour, 'gust');
        $windDirection = $this->extractNumericValue($hour, 'windDirection');
        $precipitation = $this->extractNumericValue($hour, 'precipitation');
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

        $rainSeverity = $this->resolveRainSeverity($precipitation);
        if ($rainSeverity !== null) {
            $session->rain_severity = $rainSeverity;
            $filled++;
        }

        $tideState = ($lat !== null && $lng !== null)
            ? $this->resolveTideState($targetTime, $this->fetchTideExtremes($lat, $lng, $targetTime))
            : null;
        if ($tideState !== null) {
            $session->tide_state = $tideState;
            $filled++;
        }

        $beaufort = $this->resolveBeaufort($windSpeed);
        if ($beaufort !== null) {
            $session->wind_beaufort = $beaufort;
            $filled++;
        }

        $windSeverity = $this->resolveWindSeverity($windSpeed, $windGust, $beaufort);
        if ($windSeverity !== null) {
            $session->wind_severity = $windSeverity;
            $filled++;
        }

        $temperatureSeverity = $this->resolveTemperatureSeverity($airTemperature, $waterTemperature);
        if ($temperatureSeverity !== null) {
            $session->temperature_severity = $temperatureSeverity;
            $filled++;
        }

        $forecastSeverity = $this->resolveForecastSeverity(
            $windSeverity,
            $rainSeverity,
            $temperatureSeverity,
            $waveHeight,
            $swellHeight,
            $currentSpeed !== null ? $currentSpeed * 1.943844 : null,
            $visibilityCode,
        );
        if ($forecastSeverity !== null) {
            $session->forecast_severity = $forecastSeverity;
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
            $session->tide_state ? sprintf('tide %s', $session->tide_state) : null,
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

    private function extractFields(PaddleSession $session): array
    {
        return [
            'wind_avg_ms' => $session->wind_avg_ms,
            'wind_gust_ms' => $session->wind_gust_ms,
            'wind_direction_deg' => $session->wind_direction_deg,
            'wind_beaufort' => $session->wind_beaufort,
            'tide_state' => $session->tide_state,
            'current_knots' => $session->current_knots,
            'current_direction_deg' => $session->current_direction_deg,
            'wave_height_m' => $session->wave_height_m,
            'swell_height_m' => $session->swell_height_m,
            'swell_period_s' => $session->swell_period_s,
            'swell_direction_deg' => $session->swell_direction_deg,
            'air_temp_c' => $session->air_temp_c,
            'sea_temp_c' => $session->sea_temp_c,
            'rain_severity' => $session->rain_severity,
            'wind_severity' => $session->wind_severity,
            'temperature_severity' => $session->temperature_severity,
            'forecast_severity' => $session->forecast_severity,
            'visibility_code' => $session->visibility_code,
            'weather_summary' => $session->weather_summary,
        ];
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

    private function resolveRainSeverity(?float $precipitation): ?string
    {
        if ($precipitation === null) {
            return null;
        }

        return match (true) {
            $precipitation >= 7.5 => 'extreme',
            $precipitation >= 2.5 => 'high',
            $precipitation >= 0.5 => 'moderate',
            default => 'low',
        };
    }

    private function resolveWindSeverity(?float $windSpeed, ?float $windGust, ?int $beaufort): ?string
    {
        if ($windSpeed === null && $windGust === null && $beaufort === null) {
            return null;
        }

        if (($windSpeed !== null && $windSpeed >= 17.2) || ($windGust !== null && $windGust >= 20.8) || ($beaufort !== null && $beaufort >= 8)) {
            return 'extreme';
        }

        if (($windSpeed !== null && $windSpeed >= 10.8) || ($windGust !== null && $windGust >= 13.9) || ($beaufort !== null && $beaufort >= 6)) {
            return 'high';
        }

        if (($windSpeed !== null && $windSpeed >= 5.5) || ($windGust !== null && $windGust >= 8.0) || ($beaufort !== null && $beaufort >= 4)) {
            return 'moderate';
        }

        return 'low';
    }

    private function resolveTemperatureSeverity(?float $airTemperature, ?float $waterTemperature): ?string
    {
        if ($airTemperature === null && $waterTemperature === null) {
            return null;
        }

        if (($airTemperature !== null && $airTemperature <= 0) || ($waterTemperature !== null && $waterTemperature <= 4) || ($airTemperature !== null && $airTemperature >= 30)) {
            return 'extreme';
        }

        if (($airTemperature !== null && $airTemperature <= 5) || ($waterTemperature !== null && $waterTemperature <= 7) || ($airTemperature !== null && $airTemperature >= 26)) {
            return 'high';
        }

        if (($airTemperature !== null && $airTemperature <= 10) || ($waterTemperature !== null && $waterTemperature <= 10) || ($airTemperature !== null && $airTemperature >= 22)) {
            return 'moderate';
        }

        return 'low';
    }

    private function resolveForecastSeverity(
        ?string $windSeverity,
        ?string $rainSeverity,
        ?string $temperatureSeverity,
        ?float $waveHeight,
        ?float $swellHeight,
        ?float $currentKnots,
        ?string $visibilityCode,
    ): ?string {
        $severity = $this->maxSeverity($windSeverity, $rainSeverity, $temperatureSeverity);

        if ($waveHeight !== null || $swellHeight !== null || $currentKnots !== null || $visibilityCode !== null) {
            $severity = $this->maxSeverity(
                $severity,
                match (true) {
                    $waveHeight !== null && $waveHeight >= 2.0 => 'extreme',
                    $swellHeight !== null && $swellHeight >= 2.5 => 'extreme',
                    $currentKnots !== null && $currentKnots >= 3.0 => 'extreme',
                    $visibilityCode === 'fog' => 'extreme',
                    $waveHeight !== null && $waveHeight >= 1.2 => 'high',
                    $swellHeight !== null && $swellHeight >= 1.5 => 'high',
                    $currentKnots !== null && $currentKnots >= 2.0 => 'high',
                    $visibilityCode === 'poor' => 'high',
                    $waveHeight !== null && $waveHeight >= 0.6 => 'moderate',
                    $swellHeight !== null && $swellHeight >= 0.8 => 'moderate',
                    $currentKnots !== null && $currentKnots >= 1.0 => 'moderate',
                    in_array($visibilityCode, ['moderate', 'good', 'clear'], true) => 'low',
                    default => null,
                },
            );
        }

        return $severity;
    }

    private function maxSeverity(?string ...$values): ?string
    {
        $order = [
            'low' => 1,
            'moderate' => 2,
            'high' => 3,
            'extreme' => 4,
        ];

        return collect($values)
            ->filter(fn (?string $value) => $value !== null && isset($order[$value]))
            ->sortBy(fn (string $value) => $order[$value])
            ->last();
    }

    private function resolveTideState(CarbonInterface $targetTime, array $extremes): ?string
    {
        $events = collect($extremes)
            ->map(function ($item) {
                if (! is_array($item) || empty($item['time']) || empty($item['type'])) {
                    return null;
                }

                return [
                    'time' => Carbon::parse((string) $item['time']),
                    'type' => strtolower((string) $item['type']),
                ];
            })
            ->filter()
            ->sortBy(fn (array $item) => $item['time']->timestamp)
            ->values();

        if ($events->isEmpty()) {
            return null;
        }

        $targetTimestamp = Carbon::instance($targetTime)->utc()->timestamp;

        $nearest = $events
            ->map(fn (array $item) => [
                'time' => $item['time'],
                'type' => $item['type'],
                'distance' => abs($item['time']->timestamp - $targetTimestamp),
            ])
            ->sortBy('distance')
            ->first();

        if ($nearest && $nearest['distance'] <= 45 * 60) {
            return $nearest['type'] === 'high' ? 'high' : 'low';
        }

        $previous = $events->filter(fn (array $item) => $item['time']->timestamp <= $targetTimestamp)->last();
        $next = $events->first(fn (array $item) => $item['time']->timestamp > $targetTimestamp);

        if ($previous && $next) {
            if ($previous['type'] === 'low' && $next['type'] === 'high') {
                return 'flooding';
            }

            if ($previous['type'] === 'high' && $next['type'] === 'low') {
                return 'ebbing';
            }
        }

        return 'slack';
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

    private function tideExtremesUrl(): string
    {
        return config('kayak.weather.providers.stormglass.tide_extremes_url');
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

    private function cacheSeconds(): int
    {
        return max((int) config('kayak.weather.providers.stormglass.cache_seconds', 3600), 0);
    }

    /**
     * @return array<int, string>
     */
    private function params(): array
    {
        return config('kayak.weather.providers.stormglass.params', []);
    }

    private function cacheKey(string $type, float $lat, float $lng, CarbonInterface $targetTime, string $scope): string
    {
        return sprintf(
            'stormglass:%s:%s:%s:%s:%s:%s',
            $type,
            round($lat, 4),
            round($lng, 4),
            Carbon::instance($targetTime)->utc()->format('YmdHi'),
            $this->source(),
            sha1($scope),
        );
    }

    private function requestFailureMessage(RequestException $exception): string
    {
        $status = $exception->response?->status();

        return match (true) {
            $status === 401 || $status === 403 => 'Stormglass rejected the API key. Check the key in Laravel Cloud before trying again.',
            $status === 429 => 'Stormglass daily request quota is exhausted. Try again after the reset, reduce waypoints, or upgrade the request limit.',
            $status !== null && $status >= 500 => 'Stormglass is unavailable right now. Try refreshing conditions again later.',
            $status !== null => sprintf('Stormglass request failed with HTTP %d.', $status),
            default => 'Stormglass request failed before a response was returned.',
        };
    }
}
