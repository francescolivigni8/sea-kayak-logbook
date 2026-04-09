<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsertPaddleSessionRequest;
use App\Models\PaddleSession;
use App\Models\Profile;
use App\Support\FitTrackService;
use App\Support\GpxTrackService;
use App\Support\SessionMediaService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaddleSessionController extends Controller
{
    public function __construct(
        private readonly GpxTrackService $gpxTrackService,
        private readonly FitTrackService $fitTrackService,
        private readonly SessionMediaService $media,
    ) {}

    public function index(Request $request): Response
    {
        $profile = $request->user()->resolveActiveProfile();

        $sessions = $profile->sessions()
            ->latest('session_date')
            ->latest('id')
            ->get()
            ->map(fn (PaddleSession $session) => $this->mapSessionListItem($session))
            ->values();

        return Inertia::render('sessions/Index', [
            'profile' => $this->mapProfile($profile),
            'stats' => [
                'sessionCount' => $sessions->count(),
                'distanceKm' => round((float) $profile->sessions()->sum('distance_km'), 1),
                'expeditionTrips' => (int) $profile->sessions()->where('is_expedition', true)->count(),
                'expeditionDays' => (int) $profile->sessions()->where('is_expedition', true)->sum('expedition_days'),
            ],
            'sessions' => $sessions,
        ]);
    }

    public function create(Request $request): Response
    {
        $profile = $request->user()->resolveActiveProfile();

        return Inertia::render('sessions/Create', [
            'profile' => $this->mapProfile($profile),
            'formDefaults' => $this->formDefaults($profile),
            'existingAssets' => [
                'gpxName' => null,
                'fitName' => null,
                'photoName' => null,
                'photoUrl' => null,
            ],
        ]);
    }

    public function show(Request $request, PaddleSession $session): Response
    {
        $profile = $request->user()->resolveActiveProfile();
        $this->ensureSessionBelongsToProfile($session, $profile);

        return Inertia::render('sessions/Show', [
            'profile' => $this->mapProfile($profile),
            'session' => $this->mapSessionDetail($session),
        ]);
    }

    public function store(UpsertPaddleSessionRequest $request): RedirectResponse
    {
        $profile = $request->user()->resolveActiveProfile();
        $payload = $this->buildPayload($request, $profile);

        $session = $profile->sessions()->create($payload);

        $this->storeFiles($request, $session);

        return redirect()
            ->route('sessions.edit', $session)
            ->with('success', 'Session saved. You can keep refining the notes, files, and expedition details.');
    }

    public function edit(Request $request, PaddleSession $session): Response
    {
        $profile = $request->user()->resolveActiveProfile();
        $this->ensureSessionBelongsToProfile($session, $profile);

        return Inertia::render('sessions/Edit', [
            'profile' => $this->mapProfile($profile),
            'sessionMeta' => $this->mapSessionListItem($session),
            'formDefaults' => $this->formDefaults($profile, $session),
            'existingAssets' => [
                'gpxName' => $session->garmin_gpx_name,
                'fitName' => $session->garmin_fit_name,
                'photoName' => $session->session_photo_name,
                'photoUrl' => $this->media->url($session->session_photo_path),
            ],
        ]);
    }

    public function update(UpsertPaddleSessionRequest $request, PaddleSession $session): RedirectResponse
    {
        $profile = $request->user()->resolveActiveProfile();
        $this->ensureSessionBelongsToProfile($session, $profile);

        $session->fill($this->buildPayload($request, $profile));
        $session->save();

        $this->storeFiles($request, $session);

        return back()->with('success', 'Session updated.');
    }

    private function mapProfile(Profile $profile): array
    {
        return [
            'name' => $profile->name,
            'slug' => $profile->slug,
            'homeWater' => $profile->home_water,
            'timezone' => $profile->timezone,
            'isPublic' => $profile->is_public,
            'kayaksOwned' => data_get($profile->settings, 'kayaks_owned', []),
            'paddlesOwned' => data_get($profile->settings, 'paddles_owned', []),
        ];
    }

    private function mapSessionListItem(PaddleSession $session): array
    {
        return [
            'id' => $session->id,
            'title' => $session->title,
            'date' => optional($session->session_date)->toDateString(),
            'launchName' => $session->launch_name,
            'distanceKm' => (float) $session->distance_km,
            'durationMinutes' => (int) $session->duration_minutes,
            'beaufort' => $session->wind_beaufort,
            'routeCategoryLabel' => $this->routeCategoryLabel($session->route_category),
            'isExpedition' => (bool) $session->is_expedition,
            'expeditionDays' => $session->expedition_days,
            'hasTrack' => $this->hasTrackData($session),
            'photoUrl' => $this->media->url($session->session_photo_path),
        ];
    }

    private function mapSessionDetail(PaddleSession $session): array
    {
        $averageSpeedKmh = $session->duration_minutes > 0
            ? round(((float) $session->distance_km / $session->duration_minutes) * 60, 1)
            : null;

        return [
            'id' => $session->id,
            'title' => $session->title,
            'date' => $session->session_date?->format('d M Y'),
            'startTimeLocal' => $session->start_at?->setTimezone($session->timezone)->format('H:i'),
            'timezone' => $session->timezone,
            'areaName' => $session->area_name,
            'launchName' => $session->launch_name,
            'launchLat' => $session->launch_lat !== null ? (float) $session->launch_lat : null,
            'launchLng' => $session->launch_lng !== null ? (float) $session->launch_lng : null,
            'landingName' => $session->landing_name,
            'landingLat' => $session->landing_lat !== null ? (float) $session->landing_lat : null,
            'landingLng' => $session->landing_lng !== null ? (float) $session->landing_lng : null,
            'routeCategoryLabel' => $this->routeCategoryLabel($session->route_category),
            'bodyOfWater' => $session->body_of_water,
            'kayakUsed' => $session->kayak_used,
            'paddleUsed' => $session->paddle_used,
            'distanceKm' => round((float) $session->distance_km, 1),
            'durationMinutes' => (int) $session->duration_minutes,
            'movingMinutes' => $session->moving_minutes,
            'averageSpeedKmh' => $averageSpeedKmh,
            'beaufort' => $session->wind_beaufort,
            'windAvgMs' => $session->wind_avg_ms,
            'windGustMs' => $session->wind_gust_ms,
            'tideState' => $session->tide_state,
            'currentKnots' => $session->current_knots,
            'waveHeightM' => $session->wave_height_m,
            'swellHeightM' => $session->swell_height_m,
            'swellPeriodS' => $session->swell_period_s,
            'airTempC' => $session->air_temp_c,
            'seaTempC' => $session->sea_temp_c,
            'visibilityCode' => $session->visibility_code,
            'weatherSummary' => $session->weather_summary,
            'routeSummary' => $session->route_summary,
            'notesPublic' => $session->notes_public,
            'notesPrivate' => $session->notes_private,
            'expeditionNotes' => $session->expedition_notes,
            'whatWentWell' => $session->what_went_well,
            'improveNext' => $session->improve_next,
            'skills' => $session->skills ?? [],
            'routeTags' => $session->route_tags ?? [],
            'partners' => $session->partners ?? [],
            'successfulRollsCount' => (int) $session->successful_rolls_count,
            'wetExitsCount' => (int) $session->wet_exits_count,
            'towRescuesCount' => (int) $session->tow_rescues_count,
            'confidenceScore' => $session->confidence_score,
            'fatigueScore' => $session->fatigue_score,
            'decisionScore' => $session->decision_score,
            'conditionsLogged' => (bool) $session->conditions_logged,
            'developmentLogged' => (bool) $session->development_logged,
            'isExpedition' => (bool) $session->is_expedition,
            'expeditionDays' => $session->expedition_days,
            'photoUrl' => $this->media->url($session->session_photo_path),
            'photoName' => $session->session_photo_name,
            'gpxUrl' => $this->media->url($session->gpx_path),
            'gpxName' => $session->garmin_gpx_name,
            'fitUrl' => $this->media->url($session->fit_path),
            'fitName' => $session->garmin_fit_name,
            'routeProfile' => $session->route_profile ?? [],
            'conditionRatings' => collect([
                ['label' => 'Rain', 'value' => $session->rain_severity],
                ['label' => 'Wind', 'value' => $session->wind_severity],
                ['label' => 'Temperature', 'value' => $session->temperature_severity],
                ['label' => 'Forecast', 'value' => $session->forecast_severity],
            ])->filter(fn (array $item) => filled($item['value']))->values()->all(),
        ];
    }

    private function formDefaults(Profile $profile, ?PaddleSession $session = null): array
    {
        return [
            'title' => $session?->title ?? '',
            'session_date' => optional($session?->session_date)->toDateString() ?? now()->setTimezone($profile->timezone)->toDateString(),
            'start_time_local' => $session?->start_at ? $session->start_at->setTimezone($profile->timezone)->format('H:i') : '',
            'launch_name' => $session?->launch_name ?? '',
            'launch_lat' => $session?->launch_lat !== null ? (string) $session->launch_lat : '',
            'launch_lng' => $session?->launch_lng !== null ? (string) $session->launch_lng : '',
            'landing_name' => $session?->landing_name ?? '',
            'landing_lat' => $session?->landing_lat !== null ? (string) $session->landing_lat : '',
            'landing_lng' => $session?->landing_lng !== null ? (string) $session->landing_lng : '',
            'area_name' => $session?->area_name ?? '',
            'route_category' => $session?->route_category ?? 'journey',
            'body_of_water' => $session?->body_of_water ?? 'sea',
            'kayak_used' => $session?->kayak_used ?? '',
            'paddle_used' => $session?->paddle_used ?? '',
            'distance_km' => $session?->distance_km !== null ? (string) $session->distance_km : '',
            'duration_minutes' => $session?->duration_minutes ? (string) $session->duration_minutes : '',
            'moving_minutes' => $session?->moving_minutes ? (string) $session->moving_minutes : '',
            'wind_avg_ms' => $session?->wind_avg_ms !== null ? (string) $session->wind_avg_ms : '',
            'wind_gust_ms' => $session?->wind_gust_ms !== null ? (string) $session->wind_gust_ms : '',
            'wind_direction_deg' => $session?->wind_direction_deg !== null ? (string) $session->wind_direction_deg : '',
            'wind_beaufort' => $session?->wind_beaufort !== null ? (string) $session->wind_beaufort : '',
            'tide_state' => $session?->tide_state ?? '',
            'current_knots' => $session?->current_knots !== null ? (string) $session->current_knots : '',
            'current_direction_deg' => $session?->current_direction_deg !== null ? (string) $session->current_direction_deg : '',
            'wave_height_m' => $session?->wave_height_m !== null ? (string) $session->wave_height_m : '',
            'swell_height_m' => $session?->swell_height_m !== null ? (string) $session->swell_height_m : '',
            'swell_period_s' => $session?->swell_period_s !== null ? (string) $session->swell_period_s : '',
            'swell_direction_deg' => $session?->swell_direction_deg !== null ? (string) $session->swell_direction_deg : '',
            'air_temp_c' => $session?->air_temp_c !== null ? (string) $session->air_temp_c : '',
            'sea_temp_c' => $session?->sea_temp_c !== null ? (string) $session->sea_temp_c : '',
            'rain_severity' => $session?->rain_severity ?? '',
            'wind_severity' => $session?->wind_severity ?? '',
            'temperature_severity' => $session?->temperature_severity ?? '',
            'forecast_severity' => $session?->forecast_severity ?? '',
            'visibility_code' => $session?->visibility_code ?? '',
            'weather_summary' => $session?->weather_summary ?? '',
            'route_summary' => $session?->route_summary ?? '',
            'route_tags_text' => implode(', ', $session?->route_tags ?? []),
            'partners_text' => implode(', ', $session?->partners ?? []),
            'skills_text' => implode(', ', $session?->skills ?? []),
            'successful_rolls_count' => (string) ($session?->successful_rolls_count ?? 0),
            'wet_exits_count' => (string) ($session?->wet_exits_count ?? 0),
            'tow_rescues_count' => (string) ($session?->tow_rescues_count ?? 0),
            'what_went_well' => $session?->what_went_well ?? '',
            'improve_next' => $session?->improve_next ?? '',
            'confidence_score' => $session?->confidence_score !== null ? (string) $session->confidence_score : '',
            'fatigue_score' => $session?->fatigue_score !== null ? (string) $session->fatigue_score : '',
            'decision_score' => $session?->decision_score !== null ? (string) $session->decision_score : '',
            'notes_public' => $session?->notes_public ?? '',
            'notes_private' => $session?->notes_private ?? '',
            'is_expedition' => (bool) ($session?->is_expedition ?? false),
            'expedition_days' => $session?->expedition_days !== null ? (string) $session->expedition_days : '',
            'expedition_notes' => $session?->expedition_notes ?? '',
            'is_public' => false,
        ];
    }

    private function ensureSessionBelongsToProfile(PaddleSession $session, Profile $profile): void
    {
        abort_unless($session->profile_id === $profile->id, 404);
    }

    private function buildPayload(UpsertPaddleSessionRequest $request, Profile $profile): array
    {
        $validated = $request->validated();
        $isExpedition = (bool) ($validated['is_expedition'] ?? false);

        return [
            'recorded_by_user_id' => $request->user()->id,
            'session_date' => $validated['session_date'],
            'start_at' => $this->buildStartAt($validated['session_date'], $validated['start_time_local'] ?? null, $profile->timezone),
            'timezone' => $profile->timezone,
            'title' => trim($validated['title']),
            'area_name' => $this->nullIfBlank($validated['area_name'] ?? null),
            'launch_name' => trim($validated['launch_name']),
            'launch_lat' => $this->nullableFloat($validated['launch_lat'] ?? null),
            'launch_lng' => $this->nullableFloat($validated['launch_lng'] ?? null),
            'landing_name' => $this->nullIfBlank($validated['landing_name'] ?? null),
            'landing_lat' => $this->nullableFloat($validated['landing_lat'] ?? null),
            'landing_lng' => $this->nullableFloat($validated['landing_lng'] ?? null),
            'route_category' => $validated['route_category'] ?? 'journey',
            'body_of_water' => $this->nullIfBlank($validated['body_of_water'] ?? null),
            'kayak_used' => $this->nullIfBlank($validated['kayak_used'] ?? null),
            'paddle_used' => $this->nullIfBlank($validated['paddle_used'] ?? null),
            'distance_km' => (float) ($validated['distance_km'] ?? 0),
            'duration_minutes' => (int) ($validated['duration_minutes'] ?? 0),
            'moving_minutes' => $this->nullableInt($validated['moving_minutes'] ?? null),
            'wind_avg_ms' => $this->nullableFloat($validated['wind_avg_ms'] ?? null),
            'wind_gust_ms' => $this->nullableFloat($validated['wind_gust_ms'] ?? null),
            'wind_direction_deg' => $this->nullableInt($validated['wind_direction_deg'] ?? null),
            'wind_beaufort' => $this->resolveBeaufort(
                $this->nullableInt($validated['wind_beaufort'] ?? null),
                $this->nullableFloat($validated['wind_avg_ms'] ?? null),
            ),
            'tide_state' => $this->nullIfBlank($validated['tide_state'] ?? null),
            'current_knots' => $this->nullableFloat($validated['current_knots'] ?? null),
            'current_direction_deg' => $this->nullableInt($validated['current_direction_deg'] ?? null),
            'wave_height_m' => $this->nullableFloat($validated['wave_height_m'] ?? null),
            'swell_height_m' => $this->nullableFloat($validated['swell_height_m'] ?? null),
            'swell_period_s' => $this->nullableFloat($validated['swell_period_s'] ?? null),
            'swell_direction_deg' => $this->nullableInt($validated['swell_direction_deg'] ?? null),
            'air_temp_c' => $this->nullableFloat($validated['air_temp_c'] ?? null),
            'sea_temp_c' => $this->nullableFloat($validated['sea_temp_c'] ?? null),
            'rain_severity' => $this->nullIfBlank($validated['rain_severity'] ?? null),
            'wind_severity' => $this->nullIfBlank($validated['wind_severity'] ?? null),
            'temperature_severity' => $this->nullIfBlank($validated['temperature_severity'] ?? null),
            'forecast_severity' => $this->nullIfBlank($validated['forecast_severity'] ?? null),
            'visibility_code' => $this->nullIfBlank($validated['visibility_code'] ?? null),
            'weather_summary' => $this->nullIfBlank($validated['weather_summary'] ?? null),
            'route_summary' => $this->nullIfBlank($validated['route_summary'] ?? null),
            'notes_public' => $this->nullIfBlank($validated['notes_public'] ?? null),
            'notes_private' => $this->nullIfBlank($validated['notes_private'] ?? null),
            'expedition_notes' => $this->nullIfBlank($validated['expedition_notes'] ?? null),
            'skills' => $this->explodeList($validated['skills_text'] ?? null),
            'route_tags' => $this->explodeList($validated['route_tags_text'] ?? null),
            'partners' => $this->explodeList($validated['partners_text'] ?? null),
            'successful_rolls_count' => (int) ($validated['successful_rolls_count'] ?? 0),
            'wet_exits_count' => (int) ($validated['wet_exits_count'] ?? 0),
            'tow_rescues_count' => (int) ($validated['tow_rescues_count'] ?? 0),
            'what_went_well' => $this->nullIfBlank($validated['what_went_well'] ?? null),
            'improve_next' => $this->nullIfBlank($validated['improve_next'] ?? null),
            'confidence_score' => $this->nullableInt($validated['confidence_score'] ?? null),
            'fatigue_score' => $this->nullableInt($validated['fatigue_score'] ?? null),
            'decision_score' => $this->nullableInt($validated['decision_score'] ?? null),
            'conditions_logged' => $this->hasConditionData($validated),
            'development_logged' => $this->hasDevelopmentData($validated),
            'is_expedition' => $isExpedition,
            'expedition_days' => $isExpedition ? $this->nullableInt($validated['expedition_days'] ?? null) : null,
            'is_public' => false,
        ];
    }

    private function storeFiles(UpsertPaddleSessionRequest $request, PaddleSession $session): void
    {
        $dirty = false;

        if ($request->hasFile('gpx_file')) {
            $this->deleteIfPresent($session->gpx_path);
            $file = $request->file('gpx_file');
            $summary = $this->gpxTrackService->parseFile($file->getRealPath() ?: $file->path());
            $session->gpx_path = $this->media->storeUploadedFile($file, 'gpx/manual/'.$session->profile->slug);
            $session->garmin_gpx_name = $file->getClientOriginalName();

            if ($summary) {
                $this->applyTrackSummary($session, $summary, true);
            }

            $dirty = true;
        }

        if ($request->hasFile('fit_file')) {
            $this->deleteIfPresent($session->fit_path);
            $file = $request->file('fit_file');
            $summary = $this->fitTrackService->parseFile($file->getRealPath() ?: $file->path());
            $session->fit_path = $this->media->storeUploadedFile($file, 'fit/manual/'.$session->profile->slug);
            $session->garmin_fit_name = $file->getClientOriginalName();

            if ($summary) {
                $this->applyTrackSummary($session, $summary, ! $this->hasTrackData($session));
            }

            $dirty = true;
        }

        if ($request->hasFile('session_photo')) {
            $this->deleteIfPresent($session->session_photo_path);
            $file = $request->file('session_photo');
            $session->session_photo_path = $this->media->storeUploadedFile($file, 'session-photos/'.$session->profile->slug);
            $session->session_photo_name = $file->getClientOriginalName();
            $dirty = true;
        }

        if ($dirty) {
            $session->save();
        }
    }

    private function deleteIfPresent(?string $path): void
    {
        $this->media->delete($path);
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

    private function buildStartAt(string $sessionDate, ?string $startTime, string $timezone): ?Carbon
    {
        if (! $startTime) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d H:i', $sessionDate.' '.$startTime, $timezone);
    }

    private function explodeList(?string $value): array
    {
        if (! $value) {
            return [];
        }

        return collect(explode(',', $value))
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->values()
            ->all();
    }

    private function nullIfBlank(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableFloat(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function routeCategoryLabel(?string $category): string
    {
        return match ($category) {
            'benchmark' => 'Benchmark',
            'training' => 'Training',
            'journey' => 'Journey',
            default => ucfirst(str_replace('-', ' ', (string) $category)),
        };
    }

    private function hasTrackData(PaddleSession $session): bool
    {
        return filled($session->gpx_path)
            || filled($session->fit_path)
            || (is_array($session->route_profile) && count($session->route_profile) > 1);
    }

    private function hasConditionData(array $validated): bool
    {
        foreach ([
            'wind_avg_ms',
            'wind_gust_ms',
            'wind_direction_deg',
            'wind_beaufort',
            'tide_state',
            'current_knots',
            'current_direction_deg',
            'wave_height_m',
            'swell_height_m',
            'swell_period_s',
            'swell_direction_deg',
            'air_temp_c',
            'sea_temp_c',
            'rain_severity',
            'wind_severity',
            'temperature_severity',
            'forecast_severity',
            'visibility_code',
            'weather_summary',
        ] as $field) {
            if (($validated[$field] ?? null) !== null && ($validated[$field] ?? '') !== '') {
                return true;
            }
        }

        return false;
    }

    private function hasDevelopmentData(array $validated): bool
    {
        foreach ([
            'successful_rolls_count',
            'wet_exits_count',
            'tow_rescues_count',
            'what_went_well',
            'improve_next',
            'confidence_score',
            'fatigue_score',
            'decision_score',
            'notes_private',
            'expedition_notes',
        ] as $field) {
            $value = $validated[$field] ?? null;

            if (in_array($field, ['successful_rolls_count', 'wet_exits_count', 'tow_rescues_count'], true)) {
                if ((int) $value > 0) {
                    return true;
                }

                continue;
            }

            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }

    private function resolveBeaufort(?int $manualBeaufort, ?float $windAvgMs): ?int
    {
        if ($manualBeaufort !== null) {
            return $manualBeaufort;
        }

        if ($windAvgMs === null) {
            return null;
        }

        return match (true) {
            $windAvgMs < 0.3 => 0,
            $windAvgMs < 1.6 => 1,
            $windAvgMs < 3.4 => 2,
            $windAvgMs < 5.5 => 3,
            $windAvgMs < 8.0 => 4,
            $windAvgMs < 10.8 => 5,
            $windAvgMs < 13.9 => 6,
            $windAvgMs < 17.2 => 7,
            $windAvgMs < 20.8 => 8,
            $windAvgMs < 24.5 => 9,
            $windAvgMs < 28.5 => 10,
            $windAvgMs < 32.7 => 11,
            default => 12,
        };
    }
}
