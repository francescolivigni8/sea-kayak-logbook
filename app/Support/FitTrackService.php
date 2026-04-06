<?php

namespace App\Support;

use Carbon\Carbon;
use adriangibbons\phpFITFileAnalysis;

class FitTrackService
{
    public function parseFile(string $filePath): ?array
    {
        try {
            $fit = new phpFITFileAnalysis($filePath, [
                'data_every_second' => true,
                'fix_data' => ['distance', 'lat_lon', 'speed', 'heart_rate'],
            ]);
        } catch (\Throwable) {
            return null;
        }

        $session = is_array($fit->data_mesgs['session'] ?? null)
            ? $fit->data_mesgs['session']
            : [];
        $record = is_array($fit->data_mesgs['record'] ?? null)
            ? $fit->data_mesgs['record']
            : [];

        if ($session === [] && $record === []) {
            return null;
        }

        return $this->summarize($session, $record);
    }

    private function summarize(array $session, array $record): ?array
    {
        $timestamps = array_values($record['timestamp'] ?? []);
        $firstTimestamp = $timestamps[0] ?? ($session['start_time'] ?? null);
        $lastTimestamp = $timestamps ? end($timestamps) : null;

        $rows = [];

        foreach ($timestamps as $index => $timestamp) {
            $lat = isset($record['position_lat'][$timestamp]) ? (float) $record['position_lat'][$timestamp] : null;
            $lng = isset($record['position_long'][$timestamp]) ? (float) $record['position_long'][$timestamp] : null;
            $distanceKm = isset($record['distance'][$timestamp]) ? (float) $record['distance'][$timestamp] : null;
            $speedKmh = isset($record['speed'][$timestamp]) ? ((float) $record['speed'][$timestamp] * 3.6) : 0.0;
            $heartRate = isset($record['heart_rate'][$timestamp]) ? (int) round((float) $record['heart_rate'][$timestamp]) : null;
            $temperatureC = isset($record['temperature'][$timestamp]) ? round((float) $record['temperature'][$timestamp], 1) : null;
            $minute = $firstTimestamp !== null ? max(($timestamp - $firstTimestamp) / 60, 0) : (float) $index;

            $rows[] = [
                'timestamp' => (int) $timestamp,
                'lat' => $lat !== null ? round($lat, 6) : null,
                'lng' => $lng !== null ? round($lng, 6) : null,
                'minute' => round($minute, 1),
                'distanceKm' => $distanceKm !== null ? round($distanceKm, 2) : 0.0,
                'speedKmh' => round($speedKmh, 2),
                'heartRate' => $heartRate,
                'temperatureC' => $temperatureC,
            ];
        }

        $routeProfile = $this->buildRouteProfile($rows);
        $distanceKm = $this->resolveDistanceKm($session, $rows);
        $durationMinutes = $this->resolveDurationMinutes($session, $firstTimestamp, $lastTimestamp);
        $movingMinutes = $this->resolveMovingMinutes($session);
        $averageTemperatureC = $this->resolveAverageTemperature($record);

        $startPoint = $this->resolveStartPoint($session, $rows);
        $endPoint = $this->resolveEndPoint($rows, $startPoint);
        $startAt = $firstTimestamp !== null
            ? Carbon::createFromTimestampUTC((int) $firstTimestamp)->toDateTimeString()
            : null;

        if ($distanceKm <= 0 && count($routeProfile) < 2 && $startAt === null) {
            return null;
        }

        return [
            'distanceKm' => $distanceKm,
            'durationMinutes' => $durationMinutes,
            'movingMinutes' => $movingMinutes,
            'routePoints' => collect($routeProfile)->map(fn (array $point) => $point['x'].','.$point['y'])->implode(' '),
            'routeProfile' => $routeProfile,
            'startAt' => $startAt,
            'startPoint' => $startPoint,
            'endPoint' => $endPoint,
            'averageTemperatureC' => $averageTemperatureC,
        ];
    }

    private function buildRouteProfile(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $width = 320;
        $height = 150;
        $padding = 14;
        $geoRows = array_values(array_filter($rows, fn (array $row) => $row['lat'] !== null && $row['lng'] !== null));

        if (count($geoRows) > 1) {
            $lats = array_column($geoRows, 'lat');
            $lngs = array_column($geoRows, 'lng');
            $minLat = min($lats);
            $maxLat = max($lats);
            $minLng = min($lngs);
            $maxLng = max($lngs);
            $latSpan = max($maxLat - $minLat, 0.0001);
            $lngSpan = max($maxLng - $minLng, 0.0001);

            $mapped = array_map(function (array $row) use ($padding, $width, $height, $minLat, $latSpan, $minLng, $lngSpan) {
                $x = $padding + ((($row['lng'] ?? $minLng) - $minLng) / $lngSpan) * ($width - ($padding * 2));
                $y = $padding + ((1 - ((($row['lat'] ?? $minLat) - $minLat) / $latSpan)) * ($height - ($padding * 2)));

                return [
                    ...$row,
                    'x' => round($x, 1),
                    'y' => round($y, 1),
                ];
            }, $rows);

            return $this->sampleSeries($mapped, 96);
        }

        $maxSpeed = max(array_map(fn (array $row) => max((float) $row['speedKmh'], 0.0), $rows), [1.0]);

        $mapped = array_map(function (array $row, int $index) use ($padding, $width, $height, $maxSpeed, $rows) {
            $x = $padding + ($index / max(count($rows) - 1, 1)) * ($width - ($padding * 2));
            $y = $padding + ((1 - ((float) $row['speedKmh'] / $maxSpeed)) * ($height - ($padding * 2)));

            return [
                ...$row,
                'x' => round($x, 1),
                'y' => round($y, 1),
            ];
        }, $rows, array_keys($rows));

        return $this->sampleSeries($mapped, 96);
    }

    private function sampleSeries(array $series, int $maxPoints): array
    {
        if (count($series) <= $maxPoints) {
            return $series;
        }

        $sampled = [];
        $step = (count($series) - 1) / ($maxPoints - 1);

        for ($index = 0; $index < $maxPoints; $index++) {
            $sampled[] = $series[(int) round($index * $step)];
        }

        return $sampled;
    }

    private function resolveDistanceKm(array $session, array $rows): float
    {
        if (isset($session['total_distance'])) {
            return round((float) $session['total_distance'], 2);
        }

        $lastDistance = collect($rows)->pluck('distanceKm')->filter(fn ($value) => $value > 0)->last();

        return $lastDistance !== null ? round((float) $lastDistance, 2) : 0.0;
    }

    private function resolveDurationMinutes(array $session, ?int $firstTimestamp, int|false|null $lastTimestamp): int
    {
        if (isset($session['total_elapsed_time'])) {
            return (int) round(((float) $session['total_elapsed_time']) / 60);
        }

        if ($firstTimestamp !== null && $lastTimestamp !== null && $lastTimestamp > $firstTimestamp) {
            return (int) round(($lastTimestamp - $firstTimestamp) / 60);
        }

        return 0;
    }

    private function resolveMovingMinutes(array $session): ?int
    {
        if (! isset($session['total_timer_time'])) {
            return null;
        }

        return (int) round(((float) $session['total_timer_time']) / 60);
    }

    private function resolveAverageTemperature(array $record): ?float
    {
        $temperatures = array_values($record['temperature'] ?? []);

        if ($temperatures === []) {
            return null;
        }

        return round(array_sum($temperatures) / count($temperatures), 1);
    }

    private function resolveStartPoint(array $session, array $rows): ?array
    {
        $firstGeoRow = collect($rows)->first(fn (array $row) => $row['lat'] !== null && $row['lng'] !== null);

        if ($firstGeoRow) {
            return [
                'lat' => $firstGeoRow['lat'],
                'lng' => $firstGeoRow['lng'],
                'time' => isset($firstGeoRow['timestamp'])
                    ? Carbon::createFromTimestampUTC((int) $firstGeoRow['timestamp'])->toIso8601String()
                    : null,
            ];
        }

        if (isset($session['start_position_lat'], $session['start_position_long'])) {
            return [
                'lat' => round((float) $session['start_position_lat'], 6),
                'lng' => round((float) $session['start_position_long'], 6),
                'time' => isset($session['start_time'])
                    ? Carbon::createFromTimestampUTC((int) $session['start_time'])->toIso8601String()
                    : null,
            ];
        }

        return null;
    }

    private function resolveEndPoint(array $rows, ?array $startPoint): ?array
    {
        $lastGeoRow = collect($rows)->reverse()->first(fn (array $row) => $row['lat'] !== null && $row['lng'] !== null);

        if ($lastGeoRow) {
            return [
                'lat' => $lastGeoRow['lat'],
                'lng' => $lastGeoRow['lng'],
                'time' => isset($lastGeoRow['timestamp'])
                    ? Carbon::createFromTimestampUTC((int) $lastGeoRow['timestamp'])->toIso8601String()
                    : null,
            ];
        }

        return $startPoint;
    }
}
