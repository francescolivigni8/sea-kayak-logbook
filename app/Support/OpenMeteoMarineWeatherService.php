<?php

namespace App\Support;

use App\Models\PaddleSession;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class OpenMeteoMarineWeatherService
{
    public function isConfigured(): bool
    {
        return (bool) config('kayak.weather.providers.open_meteo.enabled', true);
    }

    public function previewForecastBoard(PaddleSession $session, int $days = 5, int $stepHours = 3): array
    {
        if (! $this->isConfigured()) {
            return $this->emptyResult('skipped', 'Open-Meteo marine fallback is disabled.');
        }

        [$lat, $lng] = $this->coordinatesFor($session);

        if ($lat === null || $lng === null) {
            return $this->emptyResult('skipped', 'No saved launch or landing coordinates were available.');
        }

        $timezone = $session->timezone ?: 'UTC';
        $targetTime = $this->targetTime($session);
        $rangeStart = Carbon::parse(
            $session->session_date?->toDateString() ?? Carbon::instance($targetTime)->setTimezone($timezone)->toDateString(),
            $timezone,
        )->startOfDay();
        $rangeEnd = $rangeStart->copy()->addDays(max($days, 1))->subSecond();
        $stepHours = max($stepHours, 1);

        try {
            $weather = $this->fetchWeather($lat, $lng, $rangeStart, $rangeEnd, $timezone);
            $marine = $this->fetchMarine($lat, $lng, $rangeStart, $rangeEnd, $timezone);
        } catch (RequestException $exception) {
            report($exception);

            return $this->emptyResult(
                'failed',
                $this->requestFailureMessage($exception),
                $exception->response?->status(),
                $this->providerMessage($exception),
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->emptyResult('failed', 'Open-Meteo forecast request failed.');
        }

        $weatherHourly = $this->hourly($weather);
        $marineHourly = $this->hourly($marine);

        if (($weatherHourly['time'] ?? []) === [] && ($marineHourly['time'] ?? []) === []) {
            return $this->emptyResult('skipped', 'Open-Meteo returned no usable forecast hours.');
        }

        $targetSnapshot = $this->forecastSnapshot($weather, $marine, $targetTime, $timezone);
        $timeline = [];
        $slot = $rangeStart->copy();

        while ($slot->lte($rangeEnd)) {
            $snapshot = $this->forecastSnapshot($weather, $marine, $slot, $timezone);
            $localSlot = $slot->copy()->setTimezone($timezone);

            $timeline[] = [
                'time' => $localSlot->toIso8601String(),
                'dayLabel' => $this->dayLabel($localSlot, $timezone),
                'hourLabel' => $localSlot->format('H'),
                'status' => $snapshot['filledFields'] > 0 ? 'filled' : 'skipped',
                'filledFields' => $snapshot['filledFields'],
                'fields' => $snapshot['fields'],
            ];

            $slot->addHours($stepHours);
        }

        if (($targetSnapshot['filledFields'] ?? 0) === 0) {
            return [
                'status' => 'skipped',
                'provider' => 'open_meteo',
                'reason' => 'Open-Meteo did not return any values we could apply.',
                'filledFields' => 0,
                'fields' => [],
                'timeline' => $timeline,
            ];
        }

        return [
            'status' => 'filled',
            'provider' => 'open_meteo',
            'reason' => null,
            'filledFields' => $targetSnapshot['filledFields'],
            'fields' => $targetSnapshot['fields'],
            'timeline' => $timeline,
        ];
    }

    private function fetchWeather(float $lat, float $lng, CarbonInterface $start, CarbonInterface $end, string $timezone): array
    {
        return $this->remember(
            $this->cacheKey('weather', $lat, $lng, $start, $end, $timezone),
            fn () => Http::timeout($this->timeout())
                ->get($this->baseUrl(), $this->withApiKey([
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'hourly' => implode(',', [
                        'wind_speed_10m',
                        'wind_gusts_10m',
                        'wind_direction_10m',
                        'temperature_2m',
                        'precipitation',
                        'cloud_cover',
                        'visibility',
                    ]),
                    'start_date' => Carbon::instance($start)->setTimezone($timezone)->toDateString(),
                    'end_date' => Carbon::instance($end)->setTimezone($timezone)->toDateString(),
                    'timezone' => $timezone,
                    'wind_speed_unit' => 'ms',
                    'precipitation_unit' => 'mm',
                    'cell_selection' => 'sea',
                ]))
                ->throw()
                ->json() ?? [],
        );
    }

    private function fetchMarine(float $lat, float $lng, CarbonInterface $start, CarbonInterface $end, string $timezone): array
    {
        return $this->remember(
            $this->cacheKey('marine', $lat, $lng, $start, $end, $timezone),
            fn () => Http::timeout($this->timeout())
                ->get($this->marineBaseUrl(), $this->withApiKey([
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'hourly' => implode(',', [
                        'wave_height',
                        'swell_wave_height',
                        'swell_wave_period',
                        'swell_wave_direction',
                        'sea_surface_temperature',
                        'ocean_current_velocity',
                        'ocean_current_direction',
                        'sea_level_height_msl',
                    ]),
                    'start_date' => Carbon::instance($start)->setTimezone($timezone)->toDateString(),
                    'end_date' => Carbon::instance($end)->setTimezone($timezone)->toDateString(),
                    'timezone' => $timezone,
                    'cell_selection' => 'sea',
                ]))
                ->throw()
                ->json() ?? [],
        );
    }

    private function forecastSnapshot(array $weather, array $marine, CarbonInterface $targetTime, string $timezone): array
    {
        $weatherHourly = $this->hourly($weather);
        $marineHourly = $this->hourly($marine);
        $weatherIndex = $this->nearestHourlyIndex($weatherHourly, $targetTime, $timezone);
        $marineIndex = $this->nearestHourlyIndex($marineHourly, $targetTime, $timezone);
        $currentVelocity = $this->hourlyValue($marineHourly, 'ocean_current_velocity', $marineIndex);
        $fields = [
            'wind_avg_ms' => $this->roundedValue($this->hourlyValue($weatherHourly, 'wind_speed_10m', $weatherIndex), 1),
            'wind_gust_ms' => $this->roundedValue($this->hourlyValue($weatherHourly, 'wind_gusts_10m', $weatherIndex), 1),
            'wind_direction_deg' => $this->roundedInt($this->hourlyValue($weatherHourly, 'wind_direction_10m', $weatherIndex)),
            'current_knots' => $this->roundedValue(
                $this->currentVelocityToKnots($currentVelocity, data_get($marine, 'hourly_units.ocean_current_velocity')),
                1,
            ),
            'current_direction_deg' => $this->roundedInt($this->hourlyValue($marineHourly, 'ocean_current_direction', $marineIndex)),
            'wave_height_m' => $this->roundedValue($this->hourlyValue($marineHourly, 'wave_height', $marineIndex), 1),
            'swell_height_m' => $this->roundedValue($this->hourlyValue($marineHourly, 'swell_wave_height', $marineIndex), 1),
            'swell_period_s' => $this->roundedValue($this->hourlyValue($marineHourly, 'swell_wave_period', $marineIndex), 1),
            'swell_direction_deg' => $this->roundedInt($this->hourlyValue($marineHourly, 'swell_wave_direction', $marineIndex)),
            'air_temp_c' => $this->roundedValue($this->hourlyValue($weatherHourly, 'temperature_2m', $weatherIndex), 1),
            'sea_temp_c' => $this->roundedValue($this->hourlyValue($marineHourly, 'sea_surface_temperature', $marineIndex), 1),
            'precipitation_mm' => $this->roundedValue($this->hourlyValue($weatherHourly, 'precipitation', $weatherIndex), 1),
            'cloud_cover_percent' => $this->roundedValue($this->hourlyValue($weatherHourly, 'cloud_cover', $weatherIndex), 0),
            'visibility_code' => $this->mapVisibilityCode($this->hourlyValue($weatherHourly, 'visibility', $weatherIndex)),
            'tide_state' => $marineIndex !== null ? $this->resolveTideState($marineHourly, $marineIndex) : null,
        ];

        $fields['wind_beaufort'] = $this->resolveBeaufort($fields['wind_avg_ms']);
        $fields['rain_severity'] = $this->resolveRainSeverity($fields['precipitation_mm']);
        $fields['wind_severity'] = $this->resolveWindSeverity($fields['wind_avg_ms'], $fields['wind_gust_ms'], $fields['wind_beaufort']);
        $fields['temperature_severity'] = $this->resolveTemperatureSeverity($fields['air_temp_c'], $fields['sea_temp_c']);
        $fields['forecast_severity'] = $this->resolveForecastSeverity(
            $fields['wind_severity'],
            $fields['rain_severity'],
            $fields['temperature_severity'],
            $fields['wave_height_m'],
            $fields['swell_height_m'],
            $fields['current_knots'],
            $fields['visibility_code'],
        );
        $fields['weather_summary'] = $this->buildSummary($fields);

        $filledFields = collect($fields)
            ->reject(fn ($value) => $value === null || $value === '')
            ->count();

        return [
            'filledFields' => $filledFields,
            'fields' => $fields,
        ];
    }

    private function nearestHourlyIndex(array $hourly, CarbonInterface $targetTime, string $timezone): ?int
    {
        $times = $hourly['time'] ?? [];

        if (! is_array($times) || $times === []) {
            return null;
        }

        $targetTimestamp = Carbon::instance($targetTime)->setTimezone($timezone)->timestamp;
        $nearest = null;

        foreach ($times as $index => $time) {
            if (! is_string($time) || $time === '') {
                continue;
            }

            $timestamp = Carbon::parse($time, $timezone)->timestamp;
            $distance = abs($timestamp - $targetTimestamp);

            if ($nearest === null || $distance < $nearest['distance']) {
                $nearest = [
                    'index' => $index,
                    'distance' => $distance,
                ];
            }
        }

        return $nearest['index'] ?? null;
    }

    private function hourly(array $payload): array
    {
        $hourly = $payload['hourly'] ?? [];

        return is_array($hourly) ? $hourly : [];
    }

    private function hourlyValue(array $hourly, string $field, ?int $index): ?float
    {
        if ($index === null) {
            return null;
        }

        $values = $hourly[$field] ?? [];
        $value = is_array($values) ? ($values[$index] ?? null) : null;

        return is_numeric($value) ? (float) $value : null;
    }

    private function currentVelocityToKnots(?float $value, ?string $unit): ?float
    {
        if ($value === null) {
            return null;
        }

        $normalizedUnit = strtolower((string) $unit);

        return match (true) {
            str_contains($normalizedUnit, 'm/s') || $normalizedUnit === 'ms' => $value * 1.943844,
            str_contains($normalizedUnit, 'kn') || str_contains($normalizedUnit, 'kt') => $value,
            str_contains($normalizedUnit, 'mph') => $value * 0.868976,
            default => $value * 0.539957,
        };
    }

    private function resolveTideState(array $marineHourly, int $index): ?string
    {
        $levels = $marineHourly['sea_level_height_msl'] ?? [];

        if (! is_array($levels) || ! is_numeric($levels[$index] ?? null)) {
            return null;
        }

        $current = (float) $levels[$index];
        $previous = $this->nearestNumericLevel($levels, $index, -1);
        $next = $this->nearestNumericLevel($levels, $index, 1);

        if ($previous === null && $next === null) {
            return null;
        }

        if ($previous !== null && $next !== null) {
            if ($current >= $previous && $current >= $next && abs($current - $next) <= 0.08) {
                return 'high';
            }

            if ($current <= $previous && $current <= $next && abs($current - $next) <= 0.08) {
                return 'low';
            }

            $trend = $next - $previous;

            if (abs($trend) <= 0.04) {
                return 'slack';
            }

            return $trend > 0 ? 'flooding' : 'ebbing';
        }

        $trend = ($next ?? $current) - ($previous ?? $current);

        if (abs($trend) <= 0.04) {
            return 'slack';
        }

        return $trend > 0 ? 'flooding' : 'ebbing';
    }

    private function nearestNumericLevel(array $levels, int $index, int $direction): ?float
    {
        $candidate = $index + $direction;

        while (array_key_exists($candidate, $levels)) {
            if (is_numeric($levels[$candidate])) {
                return (float) $levels[$candidate];
            }

            $candidate += $direction;
        }

        return null;
    }

    private function buildSummary(array $fields): ?string
    {
        $parts = array_filter([
            $fields['wind_beaufort'] !== null && $fields['wind_avg_ms'] !== null
                ? sprintf('F%d / %.1f m/s wind', $fields['wind_beaufort'], $fields['wind_avg_ms'])
                : null,
            $fields['wind_gust_ms'] !== null ? sprintf('gust %.1f m/s', $fields['wind_gust_ms']) : null,
            $fields['tide_state'] ? sprintf('tide %s', $fields['tide_state']) : null,
            $fields['swell_height_m'] !== null
                ? sprintf('swell %.1f m @ %.1f s', $fields['swell_height_m'], (float) ($fields['swell_period_s'] ?? 0))
                : ($fields['wave_height_m'] !== null ? sprintf('wave %.1f m', $fields['wave_height_m']) : null),
            $fields['current_knots'] !== null ? sprintf('current %.1f kt', $fields['current_knots']) : null,
            $fields['air_temp_c'] !== null ? sprintf('air %.1f C', $fields['air_temp_c']) : null,
            $fields['sea_temp_c'] !== null ? sprintf('sea %.1f C', $fields['sea_temp_c']) : null,
            $fields['visibility_code'] ? sprintf('visibility %s', $fields['visibility_code']) : null,
        ]);

        return $parts === [] ? null : implode(', ', $parts).'.';
    }

    private function mapVisibilityCode(?float $visibility): ?string
    {
        if ($visibility === null) {
            return null;
        }

        return match (true) {
            $visibility >= 10000 => 'clear',
            $visibility >= 5000 => 'good',
            $visibility >= 2000 => 'moderate',
            $visibility >= 500 => 'poor',
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

    private function roundedValue(?float $value, int $precision): ?float
    {
        return $value === null ? null : round($value, $precision);
    }

    private function roundedInt(?float $value): ?int
    {
        return $value === null ? null : (int) round($value);
    }

    private function remember(string $key, callable $callback): array
    {
        if ($this->cacheSeconds() <= 0) {
            return $callback();
        }

        return Cache::remember($key, $this->cacheSeconds(), $callback);
    }

    private function withApiKey(array $query): array
    {
        $apiKey = trim((string) config('kayak.weather.providers.open_meteo.api_key', ''));

        if ($apiKey !== '') {
            $query['apikey'] = $apiKey;
        }

        return $query;
    }

    private function baseUrl(): string
    {
        return config('kayak.weather.providers.open_meteo.base_url');
    }

    private function marineBaseUrl(): string
    {
        return config('kayak.weather.providers.open_meteo.marine_base_url');
    }

    private function timeout(): int
    {
        return (int) config('kayak.weather.providers.open_meteo.timeout', 10);
    }

    private function cacheSeconds(): int
    {
        return max((int) config('kayak.weather.providers.open_meteo.cache_seconds', 3600), 0);
    }

    private function cacheKey(string $type, float $lat, float $lng, CarbonInterface $start, CarbonInterface $end, string $timezone): string
    {
        return sprintf(
            'open-meteo:%s:%s:%s:%s:%s:%s',
            $type,
            round($lat, 4),
            round($lng, 4),
            Carbon::instance($start)->setTimezone($timezone)->format('Ymd'),
            Carbon::instance($end)->setTimezone($timezone)->format('Ymd'),
            sha1($timezone),
        );
    }

    private function dayLabel(CarbonInterface $slot, string $timezone): string
    {
        $localSlot = Carbon::instance($slot)->setTimezone($timezone);
        $today = now($timezone)->startOfDay();

        if ($localSlot->isSameDay($today)) {
            return 'TODAY';
        }

        if ($localSlot->isSameDay($today->copy()->addDay())) {
            return 'TOMORROW';
        }

        return strtoupper($localSlot->format('D, M j'));
    }

    private function requestFailureMessage(RequestException $exception): string
    {
        $status = $exception->response?->status();
        $suffix = ($message = $this->providerMessage($exception)) ? ' Open-Meteo said: '.$message : '';

        return match (true) {
            $status === 400 => 'Open-Meteo rejected the forecast request.'.$suffix,
            $status === 401 || $status === 403 => 'Open-Meteo rejected the API key or access level.'.$suffix,
            $status === 429 => 'Open-Meteo request limit was reached. Try again later or add a customer API key.',
            $status !== null && $status >= 500 => 'Open-Meteo is unavailable right now. Try refreshing conditions again later.',
            $status !== null => sprintf('Open-Meteo request failed with HTTP %d.', $status),
            default => 'Open-Meteo request failed before a response was returned.',
        };
    }

    private function providerMessage(RequestException $exception): ?string
    {
        $response = $exception->response;

        if (! $response) {
            return null;
        }

        $message = data_get($response->json(), 'reason') ?? data_get($response->json(), 'message');

        if (is_scalar($message) && filled((string) $message)) {
            return Str::limit(trim((string) $message), 180);
        }

        $body = trim(strip_tags($response->body()));

        return $body !== '' ? Str::limit($body, 180) : null;
    }

    private function emptyResult(string $status, string $reason, ?int $httpStatus = null, ?string $providerMessage = null): array
    {
        return array_filter([
            'status' => $status,
            'provider' => 'open_meteo',
            'reason' => $reason,
            'httpStatus' => $httpStatus,
            'providerMessage' => $providerMessage,
            'filledFields' => 0,
            'fields' => [],
            'timeline' => [],
        ], fn ($value) => $value !== null);
    }
}
