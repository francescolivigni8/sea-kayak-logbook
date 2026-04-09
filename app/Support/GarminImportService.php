<?php

namespace App\Support;

use App\Models\PaddleSession;
use App\Models\Profile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GarminImportService
{
    public function __construct(
        private readonly GpxTrackService $gpxTrackService,
        private readonly FitTrackService $fitTrackService,
        private readonly SessionMediaService $media,
    ) {}

    public function import(Profile $profile, string $csvPath, ?string $gpxDirectory = null, ?string $fitDirectory = null): array
    {
        $rows = collect($this->parseCsvFile($csvPath))
            ->filter(fn (array $row) => strtolower(trim((string) ($row['Activity Type'] ?? ''))) === 'kayaking')
            ->sortBy(fn (array $row) => strtotime((string) ($row['Date'] ?? '')))
            ->values();

        $externalRefs = [];
        $sessions = collect();

        foreach ($rows as $row) {
            $record = $this->mapRowToRecord($row, $profile->timezone, $externalRefs);

            $session = PaddleSession::updateOrCreate(
                [
                    'profile_id' => $profile->id,
                    'external_ref' => $record['external_ref'],
                ],
                $record
            );

            $sessions->push($session->fresh());
        }

        $gpxSummary = [
            'matched' => 0,
            'unmatched' => [],
        ];
        $fitSummary = [
            'matched' => 0,
            'unmatched' => [],
        ];

        if ($gpxDirectory && is_dir($gpxDirectory)) {
            $gpxSummary = $this->attachGpxRoutes($profile, $sessions, $gpxDirectory);
            $sessions = $profile->sessions()
                ->whereIn('id', $sessions->pluck('id'))
                ->get();
        }

        if ($fitDirectory && is_dir($fitDirectory)) {
            $fitSummary = $this->attachFitFiles($profile, $sessions, $fitDirectory);
            $sessions = $profile->sessions()
                ->whereIn('id', $sessions->pluck('id'))
                ->get();
        }

        return [
            'imported' => $sessions->count(),
            'distanceKm' => round((float) $sessions->sum('distance_km'), 1),
            'profile' => $profile->name,
            'gpxMatched' => $gpxSummary['matched'],
            'gpxUnmatched' => $gpxSummary['unmatched'],
            'fitMatched' => $fitSummary['matched'],
            'fitUnmatched' => $fitSummary['unmatched'],
        ];
    }

    private function parseCsvFile(string $csvPath): array
    {
        $handle = fopen($csvPath, 'rb');

        if (! $handle) {
            throw new \RuntimeException("Unable to open CSV file: {$csvPath}");
        }

        $header = null;
        $rows = [];

        while (($record = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $header = $record;
                if ($header && isset($header[0])) {
                    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
                }
                continue;
            }

            if (! array_filter($record, fn ($value) => $value !== null && $value !== '')) {
                continue;
            }

            $rows[] = array_combine($header, array_pad($record, count($header), ''));
        }

        fclose($handle);

        return $rows;
    }

    private function mapRowToRecord(array $row, string $timezone, array &$externalRefs): array
    {
        $distanceKm = $this->parseNumber($row['Distance'] ?? '');
        $movingMinutes = (int) round($this->parseDurationMinutes($row['Moving Time'] ?? ''));
        $elapsedMinutes = (int) round($this->parseDurationMinutes($row['Elapsed Time'] ?? $row['Time'] ?? ''));
        $startAt = $this->parseDateTime($row['Date'] ?? null, $timezone);
        $dateText = $startAt?->toDateString() ?? now($timezone)->toDateString();
        $minTemp = $this->parseNumber($row['Min Temp'] ?? '');
        $maxTemp = $this->parseNumber($row['Max Temp'] ?? '');
        $location = $this->inferLocation($row['Title'] ?? '');
        $title = $this->inferTitle((string) ($row['Title'] ?? ''), $location['area_name'], $distanceKm, $movingMinutes);
        $category = $this->inferCategory($distanceKm, $movingMinutes);

        $baseRef = 'garmin:'.($row['Date'] ?? $dateText);
        $index = ($externalRefs[$baseRef] ?? 0) + 1;
        $externalRefs[$baseRef] = $index;
        $externalRef = $index === 1 ? $baseRef : "{$baseRef}:{$index}";

        return [
            'external_ref' => $externalRef,
            'session_date' => $dateText,
            'start_at' => $startAt,
            'timezone' => $timezone,
            'title' => $title,
            'area_name' => $location['area_name'],
            'launch_name' => $location['launch_name'],
            'landing_name' => $location['landing_name'],
            'route_category' => $category,
            'body_of_water' => 'sea',
            'distance_km' => $distanceKm,
            'duration_minutes' => $elapsedMinutes,
            'moving_minutes' => $movingMinutes ?: null,
            'air_temp_c' => $this->midpoint($minTemp, $maxTemp),
            'visibility_code' => 'good',
            'route_tags' => $this->inferTags($location['area_name'], $dateText, $distanceKm),
            'route_summary' => 'Imported from Garmin CSV. Attach route details or expand the session notes when needed.',
            'notes_private' => 'Imported from Garmin history.',
            'is_public' => false,
            'conditions_logged' => false,
            'development_logged' => false,
            'successful_rolls_count' => 0,
            'wet_exits_count' => 0,
            'tow_rescues_count' => 0,
            'is_expedition' => false,
        ];
    }

    private function attachGpxRoutes(Profile $profile, Collection $sessions, string $gpxDirectory): array
    {
        $matched = 0;
        $unmatched = [];

        $files = collect(scandir($gpxDirectory) ?: [])
            ->filter(fn (string $file) => str_ends_with(strtolower($file), '.gpx'))
            ->map(fn (string $file) => rtrim($gpxDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$file)
            ->values();

        foreach ($files as $filePath) {
            $summary = $this->gpxTrackService->parseFile($filePath);

            if (! $summary) {
                $unmatched[] = basename($filePath);
                continue;
            }

            $session = $this->matchSession($sessions, $summary, $summary['metadataTime'] ?? null);

            if (! $session) {
                $unmatched[] = basename($filePath);
                continue;
            }

            $targetPath = 'gpx/imported/'.$profile->slug.'/'.basename($filePath);
            $contents = file_get_contents($filePath);

            if ($contents === false) {
                $unmatched[] = basename($filePath);
                continue;
            }

            $this->media->putContents($targetPath, $contents);

            $session->fill([
                'gpx_path' => $targetPath,
                'garmin_gpx_name' => basename($filePath),
            ]);
            $this->applyTrackSummary($session, $summary, true);

            $session->save();
            $matched += 1;
        }

        return [
            'matched' => $matched,
            'unmatched' => $unmatched,
        ];
    }

    private function attachFitFiles(Profile $profile, Collection $sessions, string $fitDirectory): array
    {
        $matched = 0;
        $unmatched = [];

        $files = collect(scandir($fitDirectory) ?: [])
            ->filter(fn (string $file) => str_ends_with(strtolower($file), '.fit'))
            ->map(fn (string $file) => rtrim($fitDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$file)
            ->values();

        foreach ($files as $filePath) {
            $summary = $this->fitTrackService->parseFile($filePath);

            if (! $summary) {
                $unmatched[] = basename($filePath);
                continue;
            }

            $session = $this->matchSession($sessions, $summary, null);

            if (! $session) {
                $unmatched[] = basename($filePath);
                continue;
            }

            $targetPath = 'fit/imported/'.$profile->slug.'/'.basename($filePath);
            $contents = file_get_contents($filePath);

            if ($contents === false) {
                $unmatched[] = basename($filePath);
                continue;
            }

            $this->media->putContents($targetPath, $contents);

            $session->fill([
                'fit_path' => $targetPath,
                'garmin_fit_name' => basename($filePath),
            ]);
            $this->applyTrackSummary($session, $summary, ! $this->hasTrackData($session));

            $session->save();
            $matched += 1;
        }

        return [
            'matched' => $matched,
            'unmatched' => $unmatched,
        ];
    }

    private function parseNumber(string $value): float
    {
        $normalized = str_replace(',', '', trim($value));

        if ($normalized === '' || $normalized === '--') {
            return 0.0;
        }

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private function parseDurationMinutes(string $value): float
    {
        $text = trim($value);

        if ($text === '' || $text === '--') {
            return 0.0;
        }

        $parts = array_map('intval', explode(':', $text));

        if (count($parts) === 3) {
            return ($parts[0] * 60) + $parts[1] + ($parts[2] / 60);
        }

        if (count($parts) === 2) {
            return $parts[0] + ($parts[1] / 60);
        }

        return 0.0;
    }

    private function parseDateTime(?string $value, string $timezone): ?Carbon
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d H:i:s', $value, $timezone);
    }

    private function midpoint(float $minValue, float $maxValue): ?float
    {
        if ($minValue > 0 && $maxValue > 0) {
            return round(($minValue + $maxValue) / 2, 1);
        }

        if ($minValue > 0) {
            return $minValue;
        }

        if ($maxValue > 0) {
            return $maxValue;
        }

        return null;
    }

    private function inferLocation(string $title): array
    {
        $lower = strtolower($title);

        if (str_contains($lower, 'reykjanes')) {
            return [
                'area_name' => 'Reykjanes',
                'launch_name' => 'Reykjanesbaer',
                'landing_name' => 'Reykjanesbaer',
            ];
        }

        if (str_contains($lower, 'reykjavik')) {
            return [
                'area_name' => 'Faxafloi',
                'launch_name' => 'Reykjavik',
                'landing_name' => 'Reykjavik',
            ];
        }

        return [
            'area_name' => 'Faxafloi',
            'launch_name' => 'Reykjavik',
            'landing_name' => 'Reykjavik',
        ];
    }

    private function inferCategory(float $distanceKm, int $movingMinutes): string
    {
        if ($distanceKm <= 0 || $movingMinutes <= 0) {
            return 'training';
        }

        if ($distanceKm >= 15 || $movingMinutes >= 160) {
            return 'journey';
        }

        if ($distanceKm <= 6) {
            return 'benchmark';
        }

        return 'training';
    }

    private function inferTitle(string $sourceTitle, string $areaName, float $distanceKm, int $movingMinutes): string
    {
        $raw = trim($sourceTitle);

        if ($raw !== '' && strtolower($raw) !== 'kayaking') {
            return $raw;
        }

        if ($distanceKm <= 0 || $movingMinutes <= 0) {
            return "{$areaName} technical session";
        }

        if ($distanceKm >= 15) {
            return "{$areaName} longer paddle";
        }

        return "{$areaName} paddle";
    }

    private function inferTags(string $areaName, string $dateText, float $distanceKm): array
    {
        $month = (int) Carbon::parse($dateText)->month;
        $season = match (true) {
            $month === 12 || $month <= 2 => 'winter',
            $month <= 5 => 'spring',
            $month <= 8 => 'summer',
            default => 'autumn',
        };

        $location = $areaName === 'Reykjanes' ? 'reykjanes' : 'faxafloi';
        $size = $distanceKm >= 15 ? 'longer-day' : ($distanceKm >= 8 ? 'mid-distance' : 'short-day');

        return ['garmin-import', $season, $location, $size];
    }

    private function matchSession(Collection $sessions, array $summary, ?string $metadataTime): ?PaddleSession
    {
        $metadataStart = $metadataTime ? Carbon::parse($metadataTime)->toDateTimeString() : null;
        $summaryStart = $summary['startAt'];
        $normalizedDate = $metadataStart ? substr($metadataStart, 0, 10) : ($summaryStart ? substr($summaryStart, 0, 10) : null);

        $matched = $sessions->first(function (PaddleSession $session) use ($metadataStart, $summaryStart) {
            return $this->sameMinute($session->start_at?->toDateTimeString(), $metadataStart)
                || $this->sameMinute($session->start_at?->toDateTimeString(), $summaryStart);
        });

        if ($matched) {
            return $matched;
        }

        $sameDate = $sessions->filter(fn (PaddleSession $session) => $session->session_date?->toDateString() === $normalizedDate)->values();

        if ($sameDate->count() === 1) {
            return $sameDate->first();
        }

        return $sameDate->first(fn (PaddleSession $session) => ! $this->hasTrackData($session));
    }

    private function sameMinute(?string $left, ?string $right): bool
    {
        if (! $left || ! $right) {
            return false;
        }

        return substr($left, 0, 16) === substr($right, 0, 16);
    }

    private function applyTrackSummary(PaddleSession $session, array $summary, bool $replaceGeometry = false): void
    {
        $hasGeometry = is_array($session->route_profile) && count($session->route_profile) > 1;

        if (($replaceGeometry || ! $hasGeometry) && ! empty($summary['routeProfile'])) {
            $session->route_points = $summary['routePoints'] ?? $session->route_points;
            $session->route_profile = $summary['routeProfile'];
        }

        if (($replaceGeometry || ! filled($session->launch_lat)) && isset($summary['startPoint']['lat'])) {
            $session->launch_lat = $summary['startPoint']['lat'];
        }

        if (($replaceGeometry || ! filled($session->launch_lng)) && isset($summary['startPoint']['lng'])) {
            $session->launch_lng = $summary['startPoint']['lng'];
        }

        if (($replaceGeometry || ! filled($session->landing_lat)) && isset($summary['endPoint']['lat'])) {
            $session->landing_lat = $summary['endPoint']['lat'];
        }

        if (($replaceGeometry || ! filled($session->landing_lng)) && isset($summary['endPoint']['lng'])) {
            $session->landing_lng = $summary['endPoint']['lng'];
        }

        if ((float) $session->distance_km <= 0 && ($summary['distanceKm'] ?? 0) > 0) {
            $session->distance_km = $summary['distanceKm'];
        }

        if ((int) $session->duration_minutes <= 0 && ($summary['durationMinutes'] ?? 0) > 0) {
            $session->duration_minutes = $summary['durationMinutes'];
        }

        if ($session->moving_minutes === null && ($summary['movingMinutes'] ?? null) !== null) {
            $session->moving_minutes = $summary['movingMinutes'];
        }

        if ($session->start_at === null && ! empty($summary['startAt'])) {
            $session->start_at = $summary['startAt'];
        }

        if ($session->air_temp_c === null && ($summary['averageTemperatureC'] ?? null) !== null) {
            $session->air_temp_c = $summary['averageTemperatureC'];
        }
    }

    private function hasTrackData(PaddleSession $session): bool
    {
        return filled($session->gpx_path)
            || filled($session->fit_path)
            || (is_array($session->route_profile) && count($session->route_profile) > 1);
    }
}
