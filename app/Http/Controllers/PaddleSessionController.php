<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsertPaddleSessionRequest;
use App\Models\PaddleSession;
use App\Models\PlannedSession;
use App\Models\Profile;
use App\Support\FitTrackService;
use App\Support\GpxTrackService;
use App\Support\SessionMediaService;
use App\Support\StormglassWeatherService;
use App\Support\UnitPreferences;
use Carbon\Carbon;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PaddleSessionController extends Controller
{
    public function __construct(
        private readonly GpxTrackService $gpxTrackService,
        private readonly FitTrackService $fitTrackService,
        private readonly SessionMediaService $media,
        private readonly StormglassWeatherService $stormglassWeather,
    ) {}

    public function index(Request $request): Response
    {
        $profile = $request->user()->resolveActiveProfile();

        $sessions = $profile->sessions()
            ->with('categories')
            ->latest('session_date')
            ->latest('id')
            ->get()
            ->map(fn (PaddleSession $session) => $this->mapSessionListItem($session))
            ->values();

        $plannedSessions = $profile->plannedSessions()
            ->orderBy('plan_date')
            ->orderBy('id')
            ->get()
            ->map(fn (PlannedSession $plannedSession) => $this->mapPlannedSessionListItem($plannedSession))
            ->values();

        return Inertia::render('sessions/Index', [
            'profile' => $this->mapProfile($profile),
            'stats' => [
                'plannedCount' => $plannedSessions->count(),
                'sessionCount' => $sessions->count(),
                'collectionCount' => $profile->sessionCategories()->count(),
                'distanceKm' => round((float) $profile->sessions()->sum('distance_km'), 1),
                'expeditionTrips' => (int) $profile->sessions()->where('is_expedition', true)->count(),
                'expeditionDays' => (int) $profile->sessions()->where('is_expedition', true)->sum('expedition_days'),
            ],
            'plannedSessions' => $plannedSessions,
            'sessions' => $sessions,
            'categoryGroups' => $this->mapCategoryGroups($profile),
        ]);
    }

    public function create(Request $request): Response
    {
        $profile = $request->user()->resolveActiveProfile();
        $quickEntryMemory = $this->quickEntryMemory($profile);

        return Inertia::render('sessions/Create', [
            'profile' => $this->mapProfile($profile),
            'weatherAutofillAvailable' => $this->stormglassWeather->isConfigured(),
            'formDefaults' => $this->formDefaults($profile),
            'quickEntryMemory' => $quickEntryMemory,
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

    public function share(Request $request, PaddleSession $session): ViewContract
    {
        $profile = $request->user()->resolveActiveProfile();
        $this->ensureSessionBelongsToProfile($session, $profile);

        return view('sessions.share', [
            'profile' => $this->mapProfile($profile),
            'session' => $this->mapSessionDetail($session),
            'unitPreferences' => UnitPreferences::fromSettings($profile->settings ?? []),
        ]);
    }

    public function weatherPreview(Request $request): JsonResponse
    {
        $profile = $request->user()->resolveActiveProfile();
        $validated = $request->validate([
            'session_date' => ['required', 'date'],
            'start_time_local' => ['nullable', 'date_format:H:i'],
            'launch_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'launch_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'landing_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'landing_lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $previewSession = new PaddleSession([
            'session_date' => $validated['session_date'],
            'start_at' => $this->buildStartAt($validated['session_date'], $validated['start_time_local'] ?? null, $profile->timezone),
            'timezone' => $profile->timezone,
            'launch_lat' => $this->nullableFloat($validated['launch_lat'] ?? null),
            'launch_lng' => $this->nullableFloat($validated['launch_lng'] ?? null),
            'landing_lat' => $this->nullableFloat($validated['landing_lat'] ?? null),
            'landing_lng' => $this->nullableFloat($validated['landing_lng'] ?? null),
        ]);

        $result = $this->stormglassWeather->previewSession($previewSession);

        return response()->json([
            ...$result,
            'message' => match ($result['status']) {
                'filled' => sprintf('Stormglass filled %d weather fields.', (int) ($result['filledFields'] ?? 0)),
                'failed' => $result['reason'] ?? 'Stormglass preview failed.',
                default => $result['reason'] ?? 'Stormglass could not fill the weather yet.',
            },
        ]);
    }

    public function store(UpsertPaddleSessionRequest $request): RedirectResponse
    {
        $profile = $request->user()->resolveActiveProfile();
        $payload = $this->buildPayload($request, $profile);

        $session = $profile->sessions()->create($payload);
        $this->syncSessionCategories($session, $profile, $request->validated('category_names_text'));

        $this->storeFiles($request, $session);
        $successMessage = 'Session saved. You can keep refining the notes, files, and expedition details.';

        if ($request->boolean('autofill_weather')) {
            $weatherResult = $this->stormglassWeather->enrichSession($session->fresh());
            $successMessage = $this->appendWeatherMessage($successMessage, $weatherResult);
        }

        return redirect()
            ->route('dashboard')
            ->with('success', $successMessage);
    }

    public function edit(Request $request, PaddleSession $session): Response
    {
        $profile = $request->user()->resolveActiveProfile();
        $this->ensureSessionBelongsToProfile($session, $profile);

        return Inertia::render('sessions/Edit', [
            'profile' => $this->mapProfile($profile),
            'sessionMeta' => $this->mapSessionListItem($session),
            'initialStep' => $this->resolveInitialStep($request),
            'weatherAutofillAvailable' => $this->stormglassWeather->isConfigured(),
            'formDefaults' => $this->formDefaults($profile, $session),
            'quickEntryMemory' => $this->quickEntryMemory($profile, $session),
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

        $session->fill($this->buildPayload($request, $profile, $session));
        $session->save();
        $this->syncSessionCategories($session, $profile, $request->validated('category_names_text'));

        $this->storeFiles($request, $session);
        $successMessage = 'Session saved.';

        if ($request->boolean('autofill_weather')) {
            $weatherResult = $this->stormglassWeather->enrichSession($session->fresh());
            $successMessage = $this->appendWeatherMessage($successMessage, $weatherResult);
        }

        return back()->with('success', $successMessage);
    }

    private function mapProfile(Profile $profile): array
    {
        $settings = $profile->settings ?? [];

        return [
            'name' => $profile->name,
            'slug' => $profile->slug,
            'homeWater' => $profile->home_water,
            'timezone' => $profile->timezone,
            'planningUnitSystem' => in_array(data_get($settings, 'planning_unit_system'), ['metric', 'marine'], true)
                ? data_get($settings, 'planning_unit_system')
                : 'metric',
            'defaultMapView' => $this->defaultMapView($profile),
            'kayaksOwned' => data_get($settings, 'kayaks_owned', []),
            'paddlesOwned' => data_get($settings, 'paddles_owned', []),
            'sessionCategories' => $profile->sessionCategories()
                ->orderBy('name')
                ->pluck('name')
                ->values(),
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
            'hasObservation' => filled($session->notes_public),
            'photoUrl' => $this->media->url($session->session_photo_path),
            'categories' => $session->categories
                ->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ])
                ->values(),
        ];
    }

    private function mapCategoryGroups(Profile $profile): array
    {
        return $profile->sessionCategories()
            ->with(['sessions' => fn ($query) => $query
                ->latest('session_date')
                ->latest('paddle_sessions.id')])
            ->orderBy('name')
            ->get()
            ->map(function ($category) {
                $sessions = $category->sessions;

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'sessionCount' => $sessions->count(),
                    'distanceKm' => round((float) $sessions->sum('distance_km'), 1),
                    'latestDate' => optional($sessions->first()?->session_date)->toDateString(),
                    'sessions' => $sessions
                        ->take(4)
                        ->map(fn (PaddleSession $session) => [
                            'id' => $session->id,
                            'title' => $session->title,
                            'date' => optional($session->session_date)->toDateString(),
                        ])
                        ->values(),
                ];
            })
            ->values()
            ->all();
    }

    private function mapPlannedSessionListItem(PlannedSession $plannedSession): array
    {
        $pointCount = is_array($plannedSession->route_profile)
            ? count($plannedSession->route_profile)
            : 0;

        return [
            'id' => $plannedSession->id,
            'title' => $plannedSession->title,
            'date' => optional($plannedSession->plan_date)->toDateString(),
            'startTimeLocal' => $plannedSession->start_at?->setTimezone($plannedSession->timezone)->format('H:i'),
            'launchName' => $plannedSession->launch_name,
            'landingName' => $plannedSession->landing_name,
            'distanceKm' => round((float) $plannedSession->distance_km, 1),
            'estimatedDurationMinutes' => $plannedSession->estimated_duration_minutes,
            'speedKnots' => round((float) $plannedSession->speed_knots, 1),
            'pointCount' => $pointCount,
            'hasForecast' => filled($plannedSession->forecast_points),
            'notes' => $plannedSession->notes,
            'gpxUrl' => route('planning.gpx', $plannedSession),
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
            'categories' => $session->categories
                ->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ])
                ->values(),
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
            'category_names_text' => $session
                ? $session->categories()->orderBy('name')->pluck('name')->implode(', ')
                : '',
            'partners_text' => implode(', ', $session?->partners ?? []),
            'skills_text' => implode(', ', $session?->skills ?? []),
            'manual_route_waypoints' => $this->manualRouteWaypoints($session),
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
            'autofill_weather' => false,
            'is_public' => false,
        ];
    }

    private function quickEntryMemory(Profile $profile, ?PaddleSession $session = null): array
    {
        $recentSessions = $profile->sessions()
            ->select(['title', 'area_name', 'launch_name'])
            ->latest('session_date')
            ->latest('id')
            ->limit(12)
            ->get();

        $titles = $this->recentDistinctStrings($recentSessions->pluck('title')->all());
        $areas = $this->recentDistinctStrings($recentSessions->pluck('area_name')->all());
        $places = $this->recentDistinctStrings($recentSessions->pluck('launch_name')->all());

        return [
            'prefill' => [
                'title' => $session?->title ?? ($titles[0] ?? ''),
                'areaName' => $session?->area_name ?? ($areas[0] ?? ''),
                'placeName' => $session?->launch_name ?? ($places[0] ?? ''),
            ],
            'suggestions' => [
                'titles' => $titles,
                'areas' => $areas,
                'places' => $places,
            ],
        ];
    }

    private function recentDistinctStrings(array $values, int $limit = 8): array
    {
        return collect($values)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique(fn (string $value) => Str::lower($value))
            ->take($limit)
            ->values()
            ->all();
    }

    private function defaultMapView(Profile $profile): array
    {
        return [
            'lat' => (float) data_get($profile->settings, 'default_map_view.lat', 64.1670),
            'lng' => (float) data_get($profile->settings, 'default_map_view.lng', -21.8210),
            'zoom' => (int) data_get($profile->settings, 'default_map_view.zoom', 10),
        ];
    }

    private function ensureSessionBelongsToProfile(PaddleSession $session, Profile $profile): void
    {
        abort_unless($session->profile_id === $profile->id, 404);
    }

    private function buildPayload(UpsertPaddleSessionRequest $request, Profile $profile, ?PaddleSession $session = null): array
    {
        $validated = $request->validated();
        $isExpedition = (bool) ($validated['is_expedition'] ?? false);
        $launchLat = $this->nullableFloat($validated['launch_lat'] ?? null);
        $launchLng = $this->nullableFloat($validated['launch_lng'] ?? null);
        $landingLat = $this->nullableFloat($validated['landing_lat'] ?? null);
        $landingLng = $this->nullableFloat($validated['landing_lng'] ?? null);
        $canManageManualRoute = ! $request->hasFile('gpx_file')
            && ! $request->hasFile('fit_file')
            && ! filled($session?->gpx_path)
            && ! filled($session?->fit_path);
        $manualRoute = $canManageManualRoute
            ? $this->buildManualRouteSummary($launchLat, $launchLng, $landingLat, $landingLng, $validated['manual_route_waypoints'] ?? null)
            : null;

        if ($manualRoute !== null) {
            $launchLat = $manualRoute['launch_lat'];
            $launchLng = $manualRoute['launch_lng'];
            $landingLat = $manualRoute['landing_lat'];
            $landingLng = $manualRoute['landing_lng'];
        }

        $payload = [
            'recorded_by_user_id' => $request->user()->id,
            'session_date' => $validated['session_date'],
            'start_at' => $this->buildStartAt($validated['session_date'], $validated['start_time_local'] ?? null, $profile->timezone),
            'timezone' => $profile->timezone,
            'title' => trim($validated['title']),
            'area_name' => $this->nullIfBlank($validated['area_name'] ?? null),
            'launch_name' => $this->nullIfBlank($validated['launch_name'] ?? null),
            'launch_lat' => $launchLat,
            'launch_lng' => $launchLng,
            'landing_name' => $this->nullIfBlank($validated['landing_name'] ?? null),
            'landing_lat' => $landingLat,
            'landing_lng' => $landingLng,
            'route_category' => $validated['route_category'] ?? 'journey',
            'body_of_water' => $this->nullIfBlank($validated['body_of_water'] ?? null),
            'kayak_used' => $this->nullIfBlank($validated['kayak_used'] ?? null),
            'paddle_used' => $this->nullIfBlank($validated['paddle_used'] ?? null),
            'distance_km' => (float) ($manualRoute['distance_km'] ?? ($validated['distance_km'] ?? 0)),
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

        if ($canManageManualRoute) {
            $payload['route_points'] = $manualRoute['route_points'] ?? null;
            $payload['route_profile'] = $manualRoute['route_profile'] ?? null;
        }

        return $payload;
    }

    private function syncSessionCategories(PaddleSession $session, Profile $profile, ?string $namesText): void
    {
        $categoryIds = collect($this->categoryNamesFromText($namesText))
            ->map(function (string $name) use ($profile) {
                $slug = Str::slug($name) ?: 'collection';
                $category = $profile->sessionCategories()->firstOrCreate(
                    ['slug' => $slug],
                    ['name' => $name],
                );

                return $category->id;
            })
            ->values()
            ->all();

        $session->categories()->sync($categoryIds);
    }

    private function categoryNamesFromText(?string $value): array
    {
        if (! $value) {
            return [];
        }

        return collect(preg_split('/[,;\n]+/', $value) ?: [])
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique(fn (string $name) => Str::lower($name))
            ->take(12)
            ->values()
            ->all();
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
            $session->session_photo_path = $this->media->storeSanitizedImage($file, 'session-photos/'.$session->profile->slug);
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

    private function manualRouteWaypoints(?PaddleSession $session): string
    {
        if ($session === null || filled($session->gpx_path) || filled($session->fit_path) || ! is_array($session->route_profile)) {
            return '';
        }

        if (count($session->route_profile) < 2) {
            return '';
        }

        return json_encode(
            collect($session->route_profile)
                ->filter(fn (array $point) => isset($point['lat'], $point['lng']))
                ->map(fn (array $point) => [
                    'lat' => round((float) $point['lat'], 6),
                    'lng' => round((float) $point['lng'], 6),
                ])
                ->values()
                ->all(),
        ) ?: '';
    }

    private function buildManualRouteSummary(?float $launchLat, ?float $launchLng, ?float $landingLat, ?float $landingLng, ?string $waypointsJson): ?array
    {
        $routePoints = collect(json_decode($waypointsJson ?: '[]', true))
            ->filter(fn (mixed $point) => is_array($point) && isset($point['lat'], $point['lng']))
            ->map(fn (array $point) => [
                'lat' => round((float) $point['lat'], 6),
                'lng' => round((float) $point['lng'], 6),
            ])
            ->values();

        if ($routePoints->isEmpty()) {
            return [
                'launch_lat' => $launchLat,
                'launch_lng' => $launchLng,
                'landing_lat' => $landingLat,
                'landing_lng' => $landingLng,
                'distance_km' => null,
                'route_points' => null,
                'route_profile' => null,
            ];
        }

        $points = $routePoints
            ->filter(fn (array $point, int $index) => $index === 0 || $point !== $routePoints->get($index - 1))
            ->values();

        $derivedLaunch = $points->first();
        $derivedLanding = $points->count() > 1 ? $points->last() : null;

        if ($points->count() < 2) {
            return [
                'launch_lat' => $derivedLaunch['lat'] ?? $launchLat,
                'launch_lng' => $derivedLaunch['lng'] ?? $launchLng,
                'landing_lat' => null,
                'landing_lng' => null,
                'distance_km' => null,
                'route_points' => null,
                'route_profile' => null,
            ];
        }

        $lats = $points->pluck('lat')->all();
        $lngs = $points->pluck('lng')->all();
        $minLat = min($lats);
        $maxLat = max($lats);
        $minLng = min($lngs);
        $maxLng = max($lngs);
        $latSpan = max($maxLat - $minLat, 0.0001);
        $lngSpan = max($maxLng - $minLng, 0.0001);
        $width = 320;
        $height = 150;
        $padding = 14;
        $distanceKm = 0.0;

        $routeProfile = $points->values()->map(function (array $point, int $index) use ($points, &$distanceKm, $padding, $width, $height, $minLat, $latSpan, $minLng, $lngSpan) {
            if ($index > 0) {
                $previous = $points->get($index - 1);
                $distanceKm += $this->haversineKm($previous['lat'], $previous['lng'], $point['lat'], $point['lng']);
            }

            $x = $padding + ((($point['lng'] - $minLng) / $lngSpan) * ($width - ($padding * 2)));
            $y = $padding + ((1 - (($point['lat'] - $minLat) / $latSpan)) * ($height - ($padding * 2)));

            return [
                'x' => round($x, 1),
                'y' => round($y, 1),
                'lat' => $point['lat'],
                'lng' => $point['lng'],
                'minute' => 0.0,
                'distanceKm' => round($distanceKm, 2),
                'speedKmh' => 0.0,
            ];
        })->all();

        return [
            'launch_lat' => $derivedLaunch['lat'] ?? $launchLat,
            'launch_lng' => $derivedLaunch['lng'] ?? $launchLng,
            'landing_lat' => $derivedLanding['lat'] ?? null,
            'landing_lng' => $derivedLanding['lng'] ?? null,
            'distance_km' => round($distanceKm, 2),
            'route_points' => collect($routeProfile)->map(fn (array $point) => $point['x'].','.$point['y'])->implode(' '),
            'route_profile' => $routeProfile,
        ];
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

    private function haversineKm(float $leftLat, float $leftLng, float $rightLat, float $rightLng): float
    {
        $earthRadiusKm = 6371;
        $toRadians = fn (float $degrees): float => deg2rad($degrees);

        $dLat = $toRadians($rightLat - $leftLat);
        $dLng = $toRadians($rightLng - $leftLng);
        $lat1 = $toRadians($leftLat);
        $lat2 = $toRadians($rightLat);

        $a = sin($dLat / 2) ** 2 + (sin($dLng / 2) ** 2 * cos($lat1) * cos($lat2));
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
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

    private function appendWeatherMessage(string $baseMessage, array $weatherResult): string
    {
        return match ($weatherResult['status'] ?? 'skipped') {
            'filled' => $baseMessage.' Stormglass filled weather details and derived Beaufort from the saved session point.',
            'failed' => $baseMessage.' Stormglass weather autofill failed this time.',
            default => $baseMessage.' Stormglass weather autofill was skipped: '.($weatherResult['reason'] ?? 'not enough session data.'),
        };
    }

    private function resolveInitialStep(Request $request): int
    {
        return match ($request->query('step')) {
            'journey', '1', 1 => 0,
            'sea', '2', 2 => 1,
            'development', '3', 3 => 2,
            'notes', '4', 4 => 3,
            default => 0,
        };
    }
}
