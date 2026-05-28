<?php

namespace App\Support;

use Carbon\Carbon;

class GpxTrackService
{
    public function parseFile(string $filePath): ?array
    {
        $text = file_get_contents($filePath);

        if ($text === false) {
            return null;
        }

        return $this->parseXml($text);
    }

    public function parseXml(string $text): ?array
    {
        $metadataTime = $this->parseMetadataTime($text);
        $trackName = $this->parseTrackName($text);
        $track = $this->parseTrackPoints($text);

        if (count($track) < 2) {
            return null;
        }

        $summary = $this->summarizeTrack($track);
        $summary['metadataTime'] = $metadataTime;
        $summary['trackName'] = $trackName;

        return $summary;
    }

    private function parseTrackName(string $text): ?string
    {
        if (preg_match('/<trk>\s*.*?<name>([^<]+)<\/name>/s', $text, $matches)) {
            $value = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_XML1));

            return $value !== '' ? $value : null;
        }

        return null;
    }

    private function parseMetadataTime(string $text): ?string
    {
        if (preg_match('/<time>([^<]+)<\/time>/', $text, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function parseTrackPoints(string $text): array
    {
        $points = [];

        if (preg_match_all('/<trkpt[^>]*lat="([^"]+)"[^>]*lon="([^"]+)"[^>]*>(.*?)<\/trkpt>/s', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $lat = (float) $match[1];
                $lng = (float) $match[2];
                $time = null;

                if (preg_match('/<time>([^<]+)<\/time>/', $match[3], $timeMatch)) {
                    $time = $timeMatch[1];
                }

                $points[] = [
                    'lat' => $lat,
                    'lng' => $lng,
                    'time' => $time,
                ];
            }
        }

        return $points;
    }

    private function summarizeTrack(array $track): array
    {
        $distanceKm = 0.0;

        for ($index = 1; $index < count($track); $index++) {
            $distanceKm += $this->haversineKm($track[$index - 1], $track[$index]);
        }

        $firstTime = collect($track)->pluck('time')->filter()->first();
        $lastTime = collect($track)->pluck('time')->filter()->last();
        $durationMinutes = 0;

        if ($firstTime && $lastTime) {
            $delta = strtotime($lastTime) - strtotime($firstTime);
            if ($delta > 0) {
                $durationMinutes = (int) round($delta / 60);
            }
        }

        $routeProfile = $this->buildRouteProfile($track);

        return [
            'distanceKm' => round($distanceKm, 2),
            'durationMinutes' => $durationMinutes,
            'routePoints' => collect($routeProfile)->map(fn (array $point) => $point['x'].','.$point['y'])->implode(' '),
            'routeProfile' => $routeProfile,
            'startAt' => $firstTime ? Carbon::parse($firstTime)->toDateTimeString() : null,
            'startPoint' => $track[0] ?? null,
            'endPoint' => $track[count($track) - 1] ?? null,
        ];
    }

    private function buildRouteProfile(array $track): array
    {
        $width = 320;
        $height = 150;
        $padding = 14;

        $lats = array_column($track, 'lat');
        $lngs = array_column($track, 'lng');
        $minLat = min($lats);
        $maxLat = max($lats);
        $minLng = min($lngs);
        $maxLng = max($lngs);
        $latSpan = max($maxLat - $minLat, 0.0001);
        $lngSpan = max($maxLng - $minLng, 0.0001);

        $firstTime = collect($track)->pluck('time')->filter()->first();
        $firstMs = $firstTime ? strtotime($firstTime) : null;
        $distanceKm = 0.0;
        $points = [];

        foreach ($track as $index => $point) {
            if ($index > 0) {
                $distanceKm += $this->haversineKm($track[$index - 1], $point);
            }

            $currentMs = $point['time'] ? strtotime($point['time']) : null;
            $minute = ($firstMs && $currentMs) ? max(($currentMs - $firstMs) / 60, 0) : 0;
            $x = $padding + ((($point['lng'] - $minLng) / $lngSpan) * ($width - ($padding * 2)));
            $y = $padding + ((1 - (($point['lat'] - $minLat) / $latSpan)) * ($height - ($padding * 2)));

            $points[] = [
                'x' => round($x, 1),
                'y' => round($y, 1),
                'lat' => round((float) $point['lat'], 6),
                'lng' => round((float) $point['lng'], 6),
                'minute' => round($minute, 1),
                'distanceKm' => round($distanceKm, 2),
                'speedKmh' => 0.0,
            ];
        }

        $sampled = $this->sampleSeries($points, 96);

        foreach ($sampled as $index => &$point) {
            if ($index === 0) {
                continue;
            }

            $previous = $sampled[$index - 1];
            $minuteDelta = $point['minute'] - $previous['minute'];
            $distanceDelta = $point['distanceKm'] - $previous['distanceKm'];

            if ($minuteDelta > 0) {
                $point['speedKmh'] = round($distanceDelta / ($minuteDelta / 60), 2);
            } else {
                $point['speedKmh'] = $previous['speedKmh'];
            }
        }
        unset($point);

        if (isset($sampled[0], $sampled[1]) && $sampled[0]['speedKmh'] === 0.0) {
            $sampled[0]['speedKmh'] = $sampled[1]['speedKmh'];
        }

        return $sampled;
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

    private function haversineKm(array $left, array $right): float
    {
        $earthRadiusKm = 6371;
        $toRadians = fn (float $degrees): float => deg2rad($degrees);

        $dLat = $toRadians($right['lat'] - $left['lat']);
        $dLng = $toRadians($right['lng'] - $left['lng']);
        $lat1 = $toRadians($left['lat']);
        $lat2 = $toRadians($right['lat']);

        $a = sin($dLat / 2) ** 2 + (sin($dLng / 2) ** 2 * cos($lat1) * cos($lat2));
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }
}
