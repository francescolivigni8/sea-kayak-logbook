<?php

namespace App\Support;

use App\Models\PaddleSession;
use App\Models\Profile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class GarminImportService
{
    private const GENERATED_IMPORT_SEASONS = ['winter', 'spring', 'summer', 'autumn'];

    private const GENERATED_IMPORT_SIZES = ['short-day', 'mid-distance', 'longer-day'];

    public function __construct(
        private readonly GpxTrackService $gpxTrackService,
        private readonly FitTrackService $fitTrackService,
        private readonly SessionMediaService $media,
        private readonly StormglassWeatherService $stormglassWeather,
    ) {}

    public function import(
        Profile $profile,
        string $csvPath,
        ?string $gpxDirectory = null,
        ?string $fitDirectory = null,
        bool $autofillWeather = false,
        array $selectedRows = [],
    ): array
    {
        $parsedCsv = $this->parseCsvFile($csvPath);

        if ($parsedCsv['shape'] === 'single_activity_summary') {
            return $this->importSingleActivitySummary(
                $profile,
                $parsedCsv['rows'],
                $gpxDirectory,
                $fitDirectory,
                $autofillWeather,
            );
        }

        $selectedRows = collect($selectedRows)
            ->map(fn (mixed $row): int => (int) $row)
            ->filter(fn (int $row): bool => $row > 0)
            ->unique()
            ->values();

        $rows = collect($parsedCsv['rows'])
            ->filter(fn (array $row) => $this->isKayakingRow($row))
            ->filter(fn (array $row) => $this->field($row, 'date', 'activity_date', 'data', 'datum', 'fecha') !== '')
            ->when(
                $selectedRows->isNotEmpty(),
                fn (Collection $rows): Collection => $rows->filter(
                    fn (array $row): bool => $selectedRows->contains((int) ($row['__csv_row'] ?? 0)),
                ),
            )
            ->sortBy(fn (array $row) => strtotime($this->field($row, 'date', 'activity_date', 'data', 'datum', 'fecha')) ?: 0)
            ->values();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'csv_file' => 'No kayaking sessions were found in that CSV. Use Garmin Activities.csv, or upload a single-activity CSV together with exactly one GPX or FIT file.',
            ]);
        }

        $externalRefs = [];
        $sessions = collect();

        foreach ($rows as $row) {
            $record = $this->mapRowToRecord($row, $profile->timezone, $externalRefs);

            $session = $this->upsertImportedSession($profile, $record);

            $sessions->push($session->fresh());
        }

        $gpxSummary = [
            'matched' => 0,
            'unmatched' => [],
            'matchedIds' => [],
        ];
        $fitSummary = [
            'matched' => 0,
            'unmatched' => [],
            'matchedIds' => [],
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

        $weatherSummary = [
            'filled' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        if ($autofillWeather) {
            $weatherSummary = $this->stormglassWeather->enrichSessions($sessions);
        }

        return [
            'imported' => $sessions->count(),
            'distanceKm' => round((float) $sessions->sum('distance_km'), 1),
            'profile' => $profile->name,
            'gpxMatched' => $gpxSummary['matched'],
            'gpxUnmatched' => $gpxSummary['unmatched'],
            'fitMatched' => $fitSummary['matched'],
            'fitUnmatched' => $fitSummary['unmatched'],
            'weatherFilled' => $weatherSummary['filled'],
            'weatherSkipped' => $weatherSummary['skipped'],
            'weatherFailed' => $weatherSummary['failed'],
        ];
    }

    public function attachTracksToExisting(Profile $profile, ?string $gpxDirectory = null, ?string $fitDirectory = null, bool $autofillWeather = false): array
    {
        $sessions = $profile->sessions()
            ->orderBy('session_date')
            ->orderBy('start_at')
            ->get();

        $gpxSummary = [
            'matched' => 0,
            'unmatched' => [],
            'matchedIds' => [],
        ];
        $fitSummary = [
            'matched' => 0,
            'unmatched' => [],
            'matchedIds' => [],
        ];

        if ($gpxDirectory && is_dir($gpxDirectory)) {
            $gpxSummary = $this->attachGpxRoutes($profile, $sessions, $gpxDirectory);
            $sessions = $profile->sessions()
                ->whereIn('id', $sessions->pluck('id'))
                ->get();
        }

        if ($fitDirectory && is_dir($fitDirectory)) {
            $fitSummary = $this->attachFitFiles($profile, $sessions, $fitDirectory);
        }

        $matchedIds = collect($gpxSummary['matchedIds'] ?? [])
            ->merge($fitSummary['matchedIds'] ?? [])
            ->unique()
            ->values();

        $matchedSessions = $matchedIds->isNotEmpty()
            ? $profile->sessions()->whereIn('id', $matchedIds)->get()
            : collect();

        $weatherSummary = [
            'filled' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        if ($autofillWeather && $matchedSessions->isNotEmpty()) {
            $weatherSummary = $this->stormglassWeather->enrichSessions($matchedSessions);
        }

        return [
            'imported' => 0,
            'updated' => $matchedSessions->count(),
            'distanceKm' => round((float) $matchedSessions->sum('distance_km'), 1),
            'profile' => $profile->name,
            'gpxMatched' => $gpxSummary['matched'],
            'gpxUnmatched' => $gpxSummary['unmatched'],
            'fitMatched' => $fitSummary['matched'],
            'fitUnmatched' => $fitSummary['unmatched'],
            'weatherFilled' => $weatherSummary['filled'],
            'weatherSkipped' => $weatherSummary['skipped'],
            'weatherFailed' => $weatherSummary['failed'],
        ];
    }

    public function legacyLocationRepairPayload(PaddleSession $session): array
    {
        if (! str_starts_with((string) $session->external_ref, 'garmin:')) {
            return [];
        }

        $trackSummary = $this->storedGpxSummary($session);
        $titleCandidate = $this->shouldUseTitleForLocation((string) $session->title)
            ? (string) $session->title
            : null;
        $location = $this->inferLocation($trackSummary['trackName'] ?? null, $titleCandidate);

        if (! filled($location['area_name'])) {
            return [];
        }

        $updates = [];

        foreach ([
            'area_name' => 'area_name',
            'launch_name' => 'launch_name',
            'landing_name' => 'landing_name',
        ] as $field => $locationKey) {
            $incoming = $location[$locationKey] ?? null;

            if (filled($incoming) && $this->shouldReplaceImportedArea($session->{$field}) && $session->{$field} !== $incoming) {
                $updates[$field] = $incoming;
            }
        }

        $repairedTags = $this->repairGarminAreaTags($session, (string) $location['area_name']);

        if ($repairedTags !== ($session->route_tags ?? [])) {
            $updates['route_tags'] = $repairedTags;
        }

        return $updates;
    }

    private function parseCsvFile(string $csvPath): array
    {
        $handle = fopen($csvPath, 'rb');

        if (! $handle) {
            throw new \RuntimeException("Unable to open CSV file: {$csvPath}");
        }

        $firstLine = fgets($handle);
        rewind($handle);

        $delimiter = $this->detectDelimiter((string) $firstLine);
        $header = null;
        $rows = [];
        $csvRow = 0;

        while (($record = fgetcsv($handle, 0, $delimiter)) !== false) {
            $csvRow += 1;

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

            $row = array_combine($header, array_pad($record, count($header), ''));
            $row['__csv_row'] = $csvRow;
            $rows[] = $row;
        }

        fclose($handle);

        return [
            'delimiter' => $delimiter,
            'header' => $header ?? [],
            'rows' => $rows,
            'shape' => $this->detectCsvShape($header ?? []),
        ];
    }

    private function mapRowToRecord(array $row, string $timezone, array &$externalRefs): array
    {
        $distanceKm = $this->parseNumber($this->field($row, 'distance', 'distanza', 'distancia', 'distanz'));
        $movingMinutes = (int) round($this->parseDurationMinutes($this->field($row, 'moving_time', 'tempo_in_movimento', 'tempo_movimento')));
        $elapsedMinutes = (int) round($this->parseDurationMinutes($this->field($row, 'elapsed_time', 'time', 'tempo_trascorso', 'durata')));
        $startAt = $this->parseDateTime($this->field($row, 'date', 'activity_date', 'data', 'datum', 'fecha'), $timezone);
        $dateText = $startAt?->toDateString() ?? now($timezone)->toDateString();
        $minTemp = $this->parseNumber($this->field($row, 'min_temp', 'temperatura_min', 'temperatura_minima'));
        $maxTemp = $this->parseNumber($this->field($row, 'max_temp', 'temperatura_max', 'temperatura_massima'));
        $sourceTitle = $this->field($row, 'title', 'activity_name', 'name', 'titolo', 'nome');
        $sourceLocation = $this->field($row, 'location', 'place', 'course', 'route', 'percorso', 'luogo');
        $location = $this->inferLocation($sourceLocation, $sourceTitle);
        $title = $this->inferTitle($sourceTitle, $location['area_name'], $distanceKm, $movingMinutes);
        $category = $this->inferCategory($distanceKm, $movingMinutes);

        $externalDate = $this->field($row, 'date', 'activity_date', 'data', 'datum', 'fecha');
        $baseRef = 'garmin:'.($externalDate !== '' ? $externalDate : $dateText);
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

    private function importSingleActivitySummary(
        Profile $profile,
        array $rows,
        ?string $gpxDirectory,
        ?string $fitDirectory,
        bool $autofillWeather,
    ): array {
        $trackSource = $this->singleTrackSource($gpxDirectory, $fitDirectory);

        if (! $trackSource) {
            throw ValidationException::withMessages([
                'csv_file' => 'That Garmin CSV is a single-activity lap export. Upload it together with exactly one GPX or FIT file, or use Garmin Activities.csv instead.',
            ]);
        }

        $summaryRow = collect($rows)
            ->first(fn (array $row) => strtolower(trim($this->field($row, 'laps', 'giri', 'tours'))) === 'summary')
            ?? ($rows[0] ?? null);

        if (! is_array($summaryRow)) {
            throw ValidationException::withMessages([
                'csv_file' => 'That Garmin activity CSV did not contain a usable summary row.',
            ]);
        }

        $trackSummary = $trackSource['summary'];
        $startAt = ! empty($trackSummary['startAt'])
            ? Carbon::parse($trackSummary['startAt'], $profile->timezone)
            : null;
        $dateText = $startAt?->toDateString() ?? now($profile->timezone)->toDateString();
        $distanceKm = $this->parseNumber($this->field($summaryRow, 'distance', 'distanza', 'distancia', 'distanz'));
        $movingMinutes = (int) round($this->parseDurationMinutes($this->field($summaryRow, 'moving_time', 'tempo_in_movimento', 'tempo_movimento')));
        $elapsedMinutes = (int) round($this->parseDurationMinutes($this->field($summaryRow, 'time', 'elapsed_time', 'tempo_trascorso', 'durata')));
        $location = $this->inferLocation($trackSummary['trackName'] ?? null);
        $title = $this->inferSingleActivityTitle($distanceKm, $movingMinutes, $trackSummary['trackName'] ?? null);

        $session = $this->upsertImportedSession($profile, [
            'external_ref' => 'garmin:single:'.($startAt?->format('Y-m-d H:i:s') ?? $dateText),
            'session_date' => $dateText,
            'start_at' => $startAt?->toDateTimeString(),
            'timezone' => $profile->timezone,
            'title' => $title,
            'area_name' => $location['area_name'],
            'launch_name' => $location['launch_name'],
            'landing_name' => $location['landing_name'],
            'route_category' => $this->inferCategory($distanceKm, $movingMinutes),
            'body_of_water' => 'sea',
            'distance_km' => $distanceKm > 0 ? $distanceKm : ($trackSummary['distanceKm'] ?? 0.0),
            'duration_minutes' => $elapsedMinutes > 0 ? $elapsedMinutes : (int) ($trackSummary['durationMinutes'] ?? 0),
            'moving_minutes' => $movingMinutes > 0 ? $movingMinutes : (($trackSummary['movingMinutes'] ?? 0) ?: null),
            'air_temp_c' => $this->parseAverageTemperature($summaryRow),
            'visibility_code' => 'good',
            'route_tags' => $this->genericImportTags($dateText),
            'route_summary' => 'Imported from a Garmin single-activity export. Review the title and notes after import.',
            'notes_private' => 'Imported from Garmin activity CSV.',
            'is_public' => false,
            'conditions_logged' => false,
            'development_logged' => false,
            'successful_rolls_count' => 0,
            'wet_exits_count' => 0,
            'tow_rescues_count' => 0,
            'is_expedition' => false,
        ]);

        $sessions = collect([$session->fresh()]);

        $gpxSummary = [
            'matched' => 0,
            'unmatched' => [],
            'matchedIds' => [],
        ];
        $fitSummary = [
            'matched' => 0,
            'unmatched' => [],
            'matchedIds' => [],
        ];

        if ($gpxDirectory && is_dir($gpxDirectory)) {
            $gpxSummary = $this->attachGpxRoutes($profile, $sessions, $gpxDirectory);
            $sessions = $profile->sessions()->whereIn('id', $sessions->pluck('id'))->get();
        }

        if ($fitDirectory && is_dir($fitDirectory)) {
            $fitSummary = $this->attachFitFiles($profile, $sessions, $fitDirectory);
            $sessions = $profile->sessions()->whereIn('id', $sessions->pluck('id'))->get();
        }

        $weatherSummary = [
            'filled' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        if ($autofillWeather) {
            $weatherSummary = $this->stormglassWeather->enrichSessions($sessions);
        }

        return [
            'imported' => $sessions->count(),
            'distanceKm' => round((float) $sessions->sum('distance_km'), 1),
            'profile' => $profile->name,
            'gpxMatched' => $gpxSummary['matched'],
            'gpxUnmatched' => $gpxSummary['unmatched'],
            'fitMatched' => $fitSummary['matched'],
            'fitUnmatched' => $fitSummary['unmatched'],
            'weatherFilled' => $weatherSummary['filled'],
            'weatherSkipped' => $weatherSummary['skipped'],
            'weatherFailed' => $weatherSummary['failed'],
        ];
    }

    private function attachGpxRoutes(Profile $profile, Collection $sessions, string $gpxDirectory): array
    {
        $matched = 0;
        $matchedIds = [];
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
            $matchedIds[] = $session->id;
        }

        return [
            'matched' => $matched,
            'unmatched' => $unmatched,
            'matchedIds' => $matchedIds,
        ];
    }

    private function upsertImportedSession(Profile $profile, array $record): PaddleSession
    {
        $session = $this->findExistingImportedSession($profile, $record) ?? new PaddleSession([
            'profile_id' => $profile->id,
        ]);

        $session->fill($record);
        $session->profile_id = $profile->id;
        $session->save();

        return $session;
    }

    private function findExistingImportedSession(Profile $profile, array $record): ?PaddleSession
    {
        $externalRef = (string) ($record['external_ref'] ?? '');

        if ($externalRef !== '') {
            $matchedByRef = $profile->sessions()
                ->where('external_ref', $externalRef)
                ->first();

            if ($matchedByRef) {
                return $matchedByRef;
            }
        }

        $sessionDate = (string) ($record['session_date'] ?? '');

        if ($sessionDate === '') {
            return null;
        }

        $incomingStart = $this->normalizeDateTimeForMatch($record['start_at'] ?? null);
        $incomingDistance = round((float) ($record['distance_km'] ?? 0), 2);
        $incomingDuration = (int) ($record['duration_minutes'] ?? 0);

        $matches = $profile->sessions()
            ->whereDate('session_date', $sessionDate)
            ->get()
            ->filter(function (PaddleSession $session) use ($incomingDistance): bool {
                if ($incomingDistance <= 0) {
                    return false;
                }

                return abs(round((float) $session->distance_km, 2) - $incomingDistance) <= 0.15;
            })
            ->values();

        if ($matches->isEmpty()) {
            return null;
        }

        $sameMinute = $matches->first(
            fn (PaddleSession $session): bool => $this->sameMinute($session->start_at?->toDateTimeString(), $incomingStart),
        );

        if ($sameMinute) {
            return $sameMinute;
        }

        if ($matches->count() === 1) {
            return $matches->first();
        }

        return $matches
            ->sortByDesc(
                fn (PaddleSession $session): int => $this->sessionImportMatchScore($session, $incomingDuration),
            )
            ->first();
    }

    private function sessionImportMatchScore(PaddleSession $session, int $incomingDuration = 0): int
    {
        return 0
            + ($incomingDuration > 0 && abs((int) $session->duration_minutes - $incomingDuration) <= 2 ? 200 : 0)
            + (filled($session->route_points) ? 100 : 0)
            + (filled($session->garmin_gpx_name) ? 50 : 0)
            + (filled($session->route_profile) && $session->route_profile !== [] ? 25 : 0)
            + (filled($session->notes_private) ? 5 : 0)
            + (filled($session->notes_public) ? 5 : 0)
            + (filled($session->external_ref) ? 1 : 0);
    }

    private function normalizeDateTimeForMatch(mixed $value): ?string
    {
        if ($value instanceof Carbon) {
            return $value->toDateTimeString();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toDateTimeString();
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function attachFitFiles(Profile $profile, Collection $sessions, string $fitDirectory): array
    {
        $matched = 0;
        $matchedIds = [];
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
            $matchedIds[] = $session->id;
        }

        return [
            'matched' => $matched,
            'unmatched' => $unmatched,
            'matchedIds' => $matchedIds,
        ];
    }

    private function parseNumber(string $value): float
    {
        $normalized = trim($value);

        if ($normalized === '' || $normalized === '--') {
            return 0.0;
        }

        $normalized = str_replace(["\xc2\xa0", ' '], '', $normalized);
        $normalized = preg_replace('/[^0-9,\.\-]/', '', $normalized) ?? '';

        if ($normalized === '' || $normalized === '-' || $normalized === '--') {
            return 0.0;
        }

        $lastComma = strrpos($normalized, ',');
        $lastDot = strrpos($normalized, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $normalized = $lastComma > $lastDot
                ? str_replace(',', '.', str_replace('.', '', $normalized))
                : str_replace(',', '', $normalized);
        } elseif ($lastComma !== false) {
            $commaGroups = explode(',', $normalized);
            $looksLikeThousands = count($commaGroups) > 2
                && collect(array_slice($commaGroups, 1))
                    ->every(fn (string $group) => strlen($group) === 3);

            $normalized = $looksLikeThousands
                ? str_replace(',', '', $normalized)
                : str_replace(',', '.', $normalized);
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

        foreach ([
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'm/d/Y H:i:s',
            'm/d/Y H:i',
        ] as $format) {
            try {
                return Carbon::createFromFormat($format, $value, $timezone);
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($value, $timezone);
        } catch (\Throwable) {
            return null;
        }
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

    private function inferLocation(?string ...$candidates): array
    {
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);

            if ($candidate === '') {
                continue;
            }

            $lower = strtolower($candidate);

            if (str_contains($lower, 'reykjanes')) {
                return [
                    'area_name' => 'Reykjanes',
                    'launch_name' => 'Reykjanesbaer',
                    'landing_name' => 'Reykjanesbaer',
                ];
            }

            if (str_contains($lower, 'reykjavik')) {
                return [
                    'area_name' => 'Reykjavik',
                    'launch_name' => 'Reykjavik',
                    'landing_name' => 'Reykjavik',
                ];
            }

            $normalized = $this->normalizeLocationLabel($candidate);

            if ($normalized !== null) {
                return [
                    'area_name' => $normalized,
                    'launch_name' => $normalized,
                    'landing_name' => $normalized,
                ];
            }
        }

        return [
            'area_name' => null,
            'launch_name' => null,
            'landing_name' => null,
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

    private function inferTitle(string $sourceTitle, ?string $areaName, float $distanceKm, int $movingMinutes): string
    {
        $raw = trim($sourceTitle);

        if ($raw !== '' && strtolower($raw) !== 'kayaking') {
            return $raw;
        }

        $areaLabel = $areaName ?: 'Imported';

        if ($distanceKm <= 0 || $movingMinutes <= 0) {
            return "{$areaLabel} technical session";
        }

        if ($distanceKm >= 15) {
            return "{$areaLabel} longer paddle";
        }

        return "{$areaLabel} paddle";
    }

    private function inferTags(?string $areaName, string $dateText, float $distanceKm): array
    {
        $month = (int) Carbon::parse($dateText)->month;
        $season = match (true) {
            $month === 12 || $month <= 2 => 'winter',
            $month <= 5 => 'spring',
            $month <= 8 => 'summer',
            default => 'autumn',
        };

        $location = match (strtolower((string) $areaName)) {
            'reykjanes' => 'reykjanes',
            'reykjavik' => 'reykjavik',
            '', '0' => 'unknown-area',
            default => str($areaName)->lower()->slug('-')->toString() ?: 'unknown-area',
        };
        $size = $distanceKm >= 15 ? 'longer-day' : ($distanceKm >= 8 ? 'mid-distance' : 'short-day');

        return ['garmin-import', $season, $location, $size];
    }

    private function genericImportTags(string $dateText): array
    {
        $month = (int) Carbon::parse($dateText)->month;
        $season = match (true) {
            $month === 12 || $month <= 2 => 'winter',
            $month <= 5 => 'spring',
            $month <= 8 => 'summer',
            default => 'autumn',
        };

        return ['garmin-import', 'single-activity', $season];
    }

    private function isKayakingRow(array $row): bool
    {
        $activityType = strtolower(trim($this->field(
            $row,
            'activity_type',
            'type',
            'tipo_attivita',
            'tipo_de_actividad',
            'type_d_activite',
        )));

        return $activityType !== '' && str_contains($activityType, 'kayak');
    }

    private function field(array $row, string ...$aliases): string
    {
        foreach ($aliases as $alias) {
            foreach ($row as $key => $value) {
                if ($this->normalizeHeader((string) $key) === $alias) {
                    return trim((string) $value);
                }
            }
        }

        return '';
    }

    private function normalizeHeader(string $value): string
    {
        $normalized = trim($value);
        $normalized = preg_replace('/^\xEF\xBB\xBF/', '', $normalized) ?? $normalized;
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized) ?: $normalized;
        $normalized = strtolower($normalized);
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? $normalized;

        return trim($normalized, '_');
    }

    private function detectDelimiter(string $line): string
    {
        $candidates = [',', ';', "\t"];
        $bestDelimiter = ',';
        $bestColumns = 0;

        foreach ($candidates as $candidate) {
            $columns = count(str_getcsv($line, $candidate));

            if ($columns > $bestColumns) {
                $bestColumns = $columns;
                $bestDelimiter = $candidate;
            }
        }

        return $bestDelimiter;
    }

    private function detectCsvShape(array $header): string
    {
        $normalized = collect($header)->map(fn ($column) => $this->normalizeHeader((string) $column));

        if ($normalized->contains('activity_type') && $normalized->contains('date')) {
            return 'activities_export';
        }

        if ($normalized->contains('laps') && $normalized->contains('cumulative_time') && $normalized->contains('distance')) {
            return 'single_activity_summary';
        }

        return 'unknown';
    }

    private function parseAverageTemperature(array $row): ?float
    {
        $average = $this->parseNumber($this->field($row, 'avg_temperature', 'average_temperature', 'temperatura_media'));

        return $average > 0 ? $average : null;
    }

    private function inferSingleActivityTitle(float $distanceKm, int $movingMinutes, ?string $trackName = null): string
    {
        $normalizedTrackName = $this->normalizeLocationLabel((string) $trackName);

        if ($normalizedTrackName !== null) {
            return $normalizedTrackName;
        }

        if ($distanceKm >= 15 || $movingMinutes >= 160) {
            return 'Imported longer paddle';
        }

        if ($distanceKm > 0 || $movingMinutes > 0) {
            return 'Imported kayaking session';
        }

        return 'Imported Garmin session';
    }

    private function singleTrackSource(?string $gpxDirectory, ?string $fitDirectory): ?array
    {
        $sources = collect();

        if ($gpxDirectory && is_dir($gpxDirectory)) {
            $gpxFiles = collect(scandir($gpxDirectory) ?: [])
                ->filter(fn (string $file) => str_ends_with(strtolower($file), '.gpx'))
                ->values();

            if ($gpxFiles->count() === 1) {
                $path = rtrim($gpxDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$gpxFiles->first();
                $summary = $this->gpxTrackService->parseFile($path);

                if ($summary) {
                    $sources->push([
                        'type' => 'gpx',
                        'path' => $path,
                        'summary' => $summary,
                    ]);
                }
            }
        }

        if ($fitDirectory && is_dir($fitDirectory)) {
            $fitFiles = collect(scandir($fitDirectory) ?: [])
                ->filter(fn (string $file) => str_ends_with(strtolower($file), '.fit'))
                ->values();

            if ($fitFiles->count() === 1) {
                $path = rtrim($fitDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$fitFiles->first();
                $summary = $this->fitTrackService->parseFile($path);

                if ($summary) {
                    $sources->push([
                        'type' => 'fit',
                        'path' => $path,
                        'summary' => $summary,
                    ]);
                }
            }
        }

        if ($sources->count() !== 1) {
            return null;
        }

        return $sources->first();
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

        if (! empty($summary['trackName'])) {
            $location = $this->inferLocation($summary['trackName']);

            if ($this->shouldReplaceImportedArea($session->area_name) && filled($location['area_name'])) {
                $session->area_name = $location['area_name'];
            }

            if ($this->shouldReplaceImportedArea($session->launch_name) && filled($location['launch_name'])) {
                $session->launch_name = $location['launch_name'];
            }

            if ($this->shouldReplaceImportedArea($session->landing_name) && filled($location['landing_name'])) {
                $session->landing_name = $location['landing_name'];
            }
        }
    }

    private function hasTrackData(PaddleSession $session): bool
    {
        return filled($session->gpx_path)
            || filled($session->fit_path)
            || (is_array($session->route_profile) && count($session->route_profile) > 1);
    }

    private function storedGpxSummary(PaddleSession $session): ?array
    {
        if (! filled($session->gpx_path)) {
            return null;
        }

        try {
            $contents = $this->media->disk()->get((string) $session->gpx_path);
        } catch (\Throwable) {
            return null;
        }

        return is_string($contents) && $contents !== ''
            ? $this->gpxTrackService->parseXml($contents)
            : null;
    }

    private function normalizeLocationLabel(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $normalized = preg_replace('/\b(kayaking|kayak|paddle|paddling|imported|activity|session|route|loop)\b/i', '', $value) ?? $value;
        $normalized = preg_replace('/[\-_\/]+/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized, " \t\n\r\0\x0B-,:");

        if ($normalized === '' || strlen($normalized) < 3) {
            return null;
        }

        return str($normalized)->headline()->toString();
    }

    private function shouldUseTitleForLocation(string $title): bool
    {
        $normalized = strtolower(trim($title));

        if ($normalized === '') {
            return false;
        }

        if (preg_match('/^(faxafloi|imported)\s+(technical session|paddle|longer paddle|kayaking session|garmin session)$/', $normalized)) {
            return false;
        }

        return true;
    }

    private function repairGarminAreaTags(PaddleSession $session, string $areaName): array
    {
        $tags = is_array($session->route_tags) ? $session->route_tags : [];

        if (! in_array('garmin-import', $tags, true)) {
            return $tags;
        }

        $season = collect($tags)
            ->first(fn (string $tag) => in_array($tag, self::GENERATED_IMPORT_SEASONS, true))
            ?? match (true) {
                (int) $session->session_date?->month === 12 || (int) $session->session_date?->month <= 2 => 'winter',
                (int) $session->session_date?->month <= 5 => 'spring',
                (int) $session->session_date?->month <= 8 => 'summer',
                default => 'autumn',
            };

        $size = collect($tags)
            ->first(fn (string $tag) => in_array($tag, self::GENERATED_IMPORT_SIZES, true))
            ?? ((float) $session->distance_km >= 15
                ? 'longer-day'
                : ((float) $session->distance_km >= 8 ? 'mid-distance' : 'short-day'));

        $removable = collect([
            'garmin-import',
            ...self::GENERATED_IMPORT_SEASONS,
            ...self::GENERATED_IMPORT_SIZES,
            'faxafloi',
            'reykjavik',
            'reykjanes',
            'unknown-area',
            str((string) $session->area_name)->lower()->slug('-')->toString(),
            str((string) $session->launch_name)->lower()->slug('-')->toString(),
            str((string) $session->landing_name)->lower()->slug('-')->toString(),
        ])
            ->filter()
            ->unique()
            ->values()
            ->all();

        $extras = collect($tags)
            ->reject(fn (string $tag) => in_array($tag, $removable, true))
            ->values()
            ->all();

        return collect([
            'garmin-import',
            $season,
            match (strtolower(trim($areaName))) {
                'reykjanes' => 'reykjanes',
                'reykjavik' => 'reykjavik',
                default => str($areaName)->lower()->slug('-')->toString() ?: 'unknown-area',
            },
            $size,
            ...$extras,
        ])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function shouldReplaceImportedArea(?string $value): bool
    {
        $normalized = strtolower(trim((string) $value));

        return $normalized === ''
            || $normalized === 'faxafloi'
            || $normalized === 'reykjavik';
    }
}
