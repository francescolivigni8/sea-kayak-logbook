<?php

namespace App\Console\Commands;

use App\Models\PaddleSession;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ImportStaticLogbookBackup extends Command
{
    protected $signature = 'kayak:import-static-backup
        {email : The user email that owns the target Laravel profile}
        {static-dir : Absolute path to the old static sea-kayak-logbook folder}
        {--replace : Delete existing sessions in the target profile before importing}
        {--name= : Name to use if the user does not exist yet}
        {--password= : Password to set if the user does not exist yet}
    ';

    protected $description = 'Import the old static Netlify/Supabase logbook backup into Laravel, preserving route data';

    public function handle(): int
    {
        $email = trim(strtolower((string) $this->argument('email')));
        $staticDir = rtrim((string) $this->argument('static-dir'), DIRECTORY_SEPARATOR);
        $activitiesPath = $staticDir.DIRECTORY_SEPARATOR.'imported-activities.js';
        $routesPath = $staticDir.DIRECTORY_SEPARATOR.'imported-routes.js';

        if (! is_file($activitiesPath)) {
            $this->error("Static activities backup not found: {$activitiesPath}");

            return self::FAILURE;
        }

        if (! is_file($routesPath)) {
            $this->error("Static route backup not found: {$routesPath}");

            return self::FAILURE;
        }

        $user = $this->findOrCreateUser($email);
        $profile = $user->resolveActiveProfile();

        $activities = $this->readAssignedJsonArray($activitiesPath, 'GARMIN_IMPORTED_SESSIONS');
        $routeUpdates = collect($this->readAssignedJsonArray($routesPath, 'GARMIN_IMPORTED_ROUTE_UPDATES'))
            ->keyBy(fn (array $route): string => (string) ($route['id'] ?? ''));

        if ($activities === []) {
            $this->warn('No sessions found in the static backup.');

            return self::SUCCESS;
        }

        $imported = 0;
        $withRoutes = 0;

        DB::transaction(function () use ($profile, $activities, $routeUpdates, &$imported, &$withRoutes): void {
            if ($this->option('replace')) {
                $profile->sessions()->delete();
            }

            foreach ($activities as $activity) {
                $route = $routeUpdates->get((string) ($activity['id'] ?? ''));
                $payload = $this->payloadForActivity($activity, is_array($route) ? $route : []);
                $externalRef = $payload['external_ref'];

                PaddleSession::query()->updateOrCreate(
                    [
                        'profile_id' => $profile->id,
                        'external_ref' => $externalRef,
                    ],
                    $payload + [
                        'profile_id' => $profile->id,
                        'recorded_by_user_id' => $profile->owner_user_id,
                    ],
                );

                $imported++;
                if (filled($payload['route_points']) || filled($payload['garmin_gpx_name'])) {
                    $withRoutes++;
                }
            }
        });

        $this->info("Imported {$imported} sessions into {$profile->name}.");
        $this->line("Sessions with preserved route/GPX data: {$withRoutes}");
        $this->line("Profile id: {$profile->id}");

        return self::SUCCESS;
    }

    private function findOrCreateUser(string $email): User
    {
        $user = User::query()->where('email', $email)->first();

        if ($user) {
            return $user;
        }

        $name = (string) ($this->option('name') ?: Str::headline(Str::before($email, '@')));
        $password = (string) ($this->option('password') ?: 'kayaklogbook');

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $user->forceFill([
            'email_verified_at' => Carbon::now(),
            'accepted_terms_at' => Carbon::now(),
            'accepted_privacy_at' => Carbon::now(),
            'accepted_terms_version' => $user->currentTermsVersion(),
            'accepted_privacy_version' => $user->currentPrivacyVersion(),
        ])->save();

        $this->info("Created local user {$email} with password: {$password}");

        return $user;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readAssignedJsonArray(string $path, string $variable): array
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException("Unable to read {$path}");
        }

        $pattern = '/window\.'.preg_quote($variable, '/').'\s*=\s*(\[.*?\])\s*;\s*(?:window\.|$)/s';

        if (! preg_match($pattern, $contents, $matches)) {
            throw new \RuntimeException("Unable to find window.{$variable} assignment in {$path}");
        }

        $decoded = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new \RuntimeException("window.{$variable} in {$path} is not an array");
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $activity
     * @param  array<string, mixed>  $route
     * @return array<string, mixed>
     */
    private function payloadForActivity(array $activity, array $route): array
    {
        $routeProfile = $route['routeProfile'] ?? $activity['routeProfile'] ?? [];
        $routePoints = (string) ($route['routePoints'] ?? $activity['routePoints'] ?? '');
        $gpxName = (string) ($route['gpxName'] ?? $activity['gpxName'] ?? '');

        return [
            'external_ref' => 'static:'.(string) ($activity['id'] ?? Str::uuid()),
            'session_date' => (string) ($activity['date'] ?? Carbon::parse($activity['startAt'] ?? now())->toDateString()),
            'start_at' => filled($activity['startAt'] ?? null) ? Carbon::parse((string) $activity['startAt']) : null,
            'timezone' => 'Atlantic/Reykjavik',
            'title' => (string) ($activity['title'] ?? 'Kayak session'),
            'area_name' => $this->nullableString($activity['areaName'] ?? null),
            'launch_name' => $this->nullableString($activity['launchName'] ?? null),
            'launch_lat' => $this->firstRouteCoordinate($routeProfile, 'lat'),
            'launch_lng' => $this->firstRouteCoordinate($routeProfile, 'lng'),
            'landing_name' => $this->nullableString($activity['landingName'] ?? null),
            'landing_lat' => $this->lastRouteCoordinate($routeProfile, 'lat'),
            'landing_lng' => $this->lastRouteCoordinate($routeProfile, 'lng'),
            'route_category' => (string) ($activity['routeCategory'] ?? 'journey'),
            'body_of_water' => (string) ($activity['bodyOfWater'] ?? 'sea'),
            'distance_km' => (float) ($activity['distanceKm'] ?? 0),
            'duration_minutes' => (int) ($activity['durationMinutes'] ?? 0),
            'moving_minutes' => $this->nullableInt($activity['movingMinutes'] ?? null),
            'wind_avg_ms' => $this->nullableFloat($activity['windAvgMs'] ?? null),
            'wind_gust_ms' => $this->nullableFloat($activity['windGustMs'] ?? null),
            'wind_direction_deg' => $this->nullableInt($activity['windDirectionDeg'] ?? null),
            'wind_beaufort' => $this->nullableInt($activity['windBeaufort'] ?? null),
            'tide_state' => $this->nullableString($activity['tideState'] ?? null),
            'current_knots' => $this->nullableFloat($activity['currentKnots'] ?? null),
            'current_direction_deg' => $this->nullableInt($activity['currentDirectionDeg'] ?? null),
            'wave_height_m' => $this->nullableFloat($activity['waveHeightM'] ?? null),
            'swell_height_m' => $this->nullableFloat($activity['swellHeightM'] ?? null),
            'swell_period_s' => $this->nullableFloat($activity['swellPeriodS'] ?? null),
            'swell_direction_deg' => $this->nullableInt($activity['swellDirectionDeg'] ?? null),
            'air_temp_c' => $this->nullableFloat($activity['airTempC'] ?? null),
            'sea_temp_c' => $this->nullableFloat($activity['seaTempC'] ?? null),
            'rain_severity' => $this->nullableString($activity['rainSeverity'] ?? null),
            'wind_severity' => $this->nullableString($activity['windSeverity'] ?? null),
            'temperature_severity' => $this->nullableString($activity['temperatureSeverity'] ?? null),
            'forecast_severity' => $this->nullableString($activity['forecastSeverity'] ?? null),
            'visibility_code' => $this->nullableString($activity['visibilityCode'] ?? null),
            'weather_summary' => $this->nullableString($activity['weatherSummary'] ?? null),
            'route_summary' => $this->nullableString($activity['routeSummary'] ?? null),
            'notes_public' => $this->nullableString($activity['notesPublic'] ?? null),
            'notes_private' => $this->nullableString($activity['notesPrivate'] ?? null),
            'expedition_notes' => $this->nullableString($activity['expeditionNotes'] ?? null),
            'skills' => $this->arrayValue($activity['skills'] ?? []),
            'route_tags' => $this->arrayValue($activity['routeTags'] ?? []),
            'partners' => $this->arrayValue($activity['partners'] ?? []),
            'successful_rolls_count' => (int) ($activity['successfulRollsCount'] ?? 0),
            'wet_exits_count' => (int) (($activity['wetExitsCount'] ?? 0) + ($activity['swimsCount'] ?? 0)),
            'tow_rescues_count' => (int) ($activity['towRescuesCount'] ?? 0),
            'what_went_well' => $this->nullableString($activity['whatWentWell'] ?? null),
            'improve_next' => $this->nullableString($activity['improveNext'] ?? null),
            'confidence_score' => $this->scoreValue($activity['confidenceScore'] ?? null),
            'fatigue_score' => $this->scoreValue($activity['fatigueScore'] ?? null),
            'decision_score' => $this->scoreValue($activity['decisionScore'] ?? null),
            'conditions_logged' => (bool) ($activity['conditionsLogged'] ?? false),
            'development_logged' => (bool) ($activity['developmentLogged'] ?? false),
            'is_expedition' => (bool) ($activity['isExpedition'] ?? false),
            'expedition_days' => $this->nullableInt($activity['expeditionDays'] ?? null),
            'route_points' => $routePoints !== '' ? $routePoints : null,
            'route_profile' => is_array($routeProfile) ? $routeProfile : [],
            'garmin_gpx_name' => $gpxName !== '' ? $gpxName : null,
            'garmin_fit_name' => $this->nullableString($activity['fitName'] ?? null),
            'is_public' => (bool) ($activity['isPublic'] ?? false),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    /**
     * @return array<int, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    private function scoreValue(mixed $value): ?int
    {
        $score = $this->nullableInt($value);

        return $score !== null && $score >= 1 && $score <= 5 ? $score : null;
    }

    /**
     * @param  array<int, array<string, mixed>>|mixed  $routeProfile
     */
    private function firstRouteCoordinate(mixed $routeProfile, string $key): ?float
    {
        if (! is_array($routeProfile) || ! isset($routeProfile[0][$key])) {
            return null;
        }

        return $this->nullableFloat($routeProfile[0][$key]);
    }

    /**
     * @param  array<int, array<string, mixed>>|mixed  $routeProfile
     */
    private function lastRouteCoordinate(mixed $routeProfile, string $key): ?float
    {
        if (! is_array($routeProfile) || $routeProfile === []) {
            return null;
        }

        $last = $routeProfile[array_key_last($routeProfile)];

        return is_array($last) ? $this->nullableFloat($last[$key] ?? null) : null;
    }
}
