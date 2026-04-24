<?php

namespace App\Http\Controllers;

use App\Models\PaddleSession;
use App\Models\PlannedSession;
use App\Models\Profile;
use App\Support\PlanningForecastService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlanningController extends Controller
{
    private const MAX_FORECAST_OFFSET_MINUTES = 1440;

    public function __construct(
        private readonly PlanningForecastService $planningForecast,
    ) {}

    public function index(Request $request): Response
    {
        $profile = $request->user()->resolveActiveProfile();

        return $this->renderPlanner($profile);
    }

    public function edit(Request $request, PlannedSession $plannedSession): Response
    {
        $profile = $request->user()->resolveActiveProfile();
        $this->ensurePlanBelongsToProfile($plannedSession, $profile);

        return $this->renderPlanner($profile, $plannedSession);
    }

    public function store(Request $request): RedirectResponse
    {
        $profile = $request->user()->resolveActiveProfile();

        $plannedSession = $profile->plannedSessions()->create(
            $this->buildPayload($request, $profile),
        );

        return redirect()
            ->route('planning.edit', $plannedSession)
            ->with('success', 'Planned session saved. It is now in Library under Planned sessions.');
    }

    public function update(Request $request, PlannedSession $plannedSession): RedirectResponse
    {
        $profile = $request->user()->resolveActiveProfile();
        $this->ensurePlanBelongsToProfile($plannedSession, $profile);

        $plannedSession->fill($this->buildPayload($request, $profile));
        $plannedSession->save();

        return back()->with('success', 'Planned session updated.');
    }

    public function gpx(Request $request, PlannedSession $plannedSession): StreamedResponse
    {
        $profile = $request->user()->resolveActiveProfile();
        $this->ensurePlanBelongsToProfile($plannedSession, $profile);

        $gpx = $this->plannedSessionGpx($plannedSession);
        abort_if($gpx === null, 404);

        $filename = (Str::slug($plannedSession->title ?: 'planned-route') ?: 'planned-route')
            .'-'.$plannedSession->id.'.gpx';

        return response()->streamDownload(
            fn () => print $gpx,
            $filename,
            ['Content-Type' => 'application/gpx+xml; charset=utf-8'],
        );
    }

    private function renderPlanner(Profile $profile, ?PlannedSession $plannedSession = null): Response
    {
        return Inertia::render('planning/Index', [
            'profile' => $this->mapProfile($profile),
            'weatherAutofillAvailable' => $this->planningForecast->isConfigured(),
            'formDefaults' => $this->formDefaults($profile, $plannedSession),
            'plannedSession' => $plannedSession ? $this->mapPlannedSession($plannedSession) : null,
        ]);
    }

    public function weatherPreview(Request $request): JsonResponse
    {
        $profile = $request->user()->resolveActiveProfile();
        $validated = $request->validate([
            'plan_date' => ['required', 'date'],
            'start_time_local' => ['nullable', 'date_format:H:i'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'label' => ['nullable', 'string', 'max:80'],
            'offset_minutes' => ['nullable', 'integer', 'min:0'],
        ]);
        $startAt = $this->buildStartAt($validated['plan_date'], $validated['start_time_local'] ?? null, $profile->timezone);
        $offsetMinutes = min(
            (int) ($validated['offset_minutes'] ?? 0),
            self::MAX_FORECAST_OFFSET_MINUTES,
        );

        if ($startAt && isset($validated['offset_minutes'])) {
            $startAt = $startAt->copy()->addMinutes($offsetMinutes);
        }

        $previewSession = new PaddleSession([
            'session_date' => $validated['plan_date'],
            'start_at' => $startAt,
            'timezone' => $profile->timezone,
            'launch_lat' => $this->nullableFloat($validated['lat'] ?? null),
            'launch_lng' => $this->nullableFloat($validated['lng'] ?? null),
        ]);

        $result = $this->planningForecast->previewForecastBoard($previewSession);

        return response()->json([
            ...$result,
            'point' => [
                'label' => $validated['label'] ?? 'Route area',
                'lat' => $this->nullableFloat($validated['lat'] ?? null),
                'lng' => $this->nullableFloat($validated['lng'] ?? null),
                'offsetMinutes' => $offsetMinutes,
            ],
            'message' => match ($result['status']) {
                'filled' => sprintf('Forecast board filled %d fields for %s.', (int) ($result['filledFields'] ?? 0), $validated['label'] ?? 'route area'),
                'failed' => $result['reason'] ?? 'Forecast preview failed.',
                default => $result['reason'] ?? 'Forecast could not fill this route area yet.',
            },
        ]);
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
            'hasCustomDefaultMapView' => is_array(data_get($settings, 'default_map_view')),
        ];
    }

    private function mapPlannedSession(PlannedSession $plannedSession): array
    {
        return [
            'id' => $plannedSession->id,
            'status' => $plannedSession->status,
            'title' => $plannedSession->title,
            'planDate' => optional($plannedSession->plan_date)->toDateString(),
            'startTimeLocal' => $plannedSession->start_at?->setTimezone($plannedSession->timezone)->format('H:i'),
            'timezone' => $plannedSession->timezone,
            'launchName' => $plannedSession->launch_name,
            'launchLat' => $plannedSession->launch_lat !== null ? (string) $plannedSession->launch_lat : '',
            'launchLng' => $plannedSession->launch_lng !== null ? (string) $plannedSession->launch_lng : '',
            'landingName' => $plannedSession->landing_name,
            'landingLat' => $plannedSession->landing_lat !== null ? (string) $plannedSession->landing_lat : '',
            'landingLng' => $plannedSession->landing_lng !== null ? (string) $plannedSession->landing_lng : '',
            'speedKnots' => (string) $plannedSession->speed_knots,
            'distanceKm' => round((float) $plannedSession->distance_km, 1),
            'estimatedDurationMinutes' => $plannedSession->estimated_duration_minutes,
            'routeWaypointsJson' => $this->routeWaypointsJson($plannedSession),
            'forecastByPoint' => $plannedSession->forecast_points ?? [],
            'notes' => $plannedSession->notes ?? '',
            'updatedAt' => $plannedSession->updated_at?->toIso8601String(),
            'gpxUrl' => route('planning.gpx', $plannedSession),
        ];
    }

    private function formDefaults(Profile $profile, ?PlannedSession $plannedSession = null): array
    {
        return [
            'title' => $plannedSession?->title ?? '',
            'plan_date' => optional($plannedSession?->plan_date)->toDateString() ?? now($profile->timezone)->toDateString(),
            'start_time_local' => $plannedSession?->start_at ? $plannedSession->start_at->setTimezone($profile->timezone)->format('H:i') : '09:00',
            'speed_knots' => $plannedSession?->speed_knots !== null ? (string) $plannedSession->speed_knots : '3.5',
            'launch_name' => $plannedSession?->launch_name ?? '',
            'launch_lat' => $plannedSession?->launch_lat !== null ? (string) $plannedSession->launch_lat : '',
            'launch_lng' => $plannedSession?->launch_lng !== null ? (string) $plannedSession->launch_lng : '',
            'landing_name' => $plannedSession?->landing_name ?? '',
            'landing_lat' => $plannedSession?->landing_lat !== null ? (string) $plannedSession->landing_lat : '',
            'landing_lng' => $plannedSession?->landing_lng !== null ? (string) $plannedSession->landing_lng : '',
            'route_waypoints' => $plannedSession ? $this->routeWaypointsJson($plannedSession) : '',
            'forecast_points' => $plannedSession && $plannedSession->forecast_points ? json_encode($plannedSession->forecast_points) : '',
            'notes' => $plannedSession?->notes ?? '',
        ];
    }

    private function defaultMapView(Profile $profile): array
    {
        return [
            'lat' => (float) data_get($profile->settings, 'default_map_view.lat', 64.1670),
            'lng' => (float) data_get($profile->settings, 'default_map_view.lng', -21.8210),
            'zoom' => (int) data_get($profile->settings, 'default_map_view.zoom', 10),
        ];
    }

    private function buildStartAt(string $planDate, ?string $startTime, string $timezone): ?Carbon
    {
        if (! $startTime) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d H:i', $planDate.' '.$startTime, $timezone);
    }

    private function ensurePlanBelongsToProfile(PlannedSession $plannedSession, Profile $profile): void
    {
        abort_unless($plannedSession->profile_id === $profile->id, 404);
    }

    private function buildPayload(Request $request, Profile $profile): array
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'plan_date' => ['required', 'date'],
            'start_time_local' => ['nullable', 'date_format:H:i'],
            'speed_knots' => ['required', 'numeric', 'min:0', 'max:20'],
            'launch_name' => ['nullable', 'string', 'max:255'],
            'launch_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'launch_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'landing_name' => ['nullable', 'string', 'max:255'],
            'landing_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'landing_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'route_waypoints' => ['nullable', 'json'],
            'forecast_points' => ['nullable', 'json'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $launchLat = $this->nullableFloat($validated['launch_lat'] ?? null);
        $launchLng = $this->nullableFloat($validated['launch_lng'] ?? null);
        $landingLat = $this->nullableFloat($validated['landing_lat'] ?? null);
        $landingLng = $this->nullableFloat($validated['landing_lng'] ?? null);
        $speedKnots = max((float) ($validated['speed_knots'] ?? 0), 0);
        $route = $this->buildRouteSummary(
            $launchLat,
            $launchLng,
            $landingLat,
            $landingLng,
            $validated['route_waypoints'] ?? null,
            $speedKnots,
        );

        return [
            'created_by_user_id' => $request->user()->id,
            'status' => 'planned',
            'plan_date' => $validated['plan_date'],
            'start_at' => $this->buildStartAt($validated['plan_date'], $validated['start_time_local'] ?? null, $profile->timezone),
            'timezone' => $profile->timezone,
            'title' => $this->nullIfBlank($validated['title'] ?? null) ?? 'Planned paddle',
            'launch_name' => $this->nullIfBlank($validated['launch_name'] ?? null),
            'launch_lat' => $launchLat,
            'launch_lng' => $launchLng,
            'landing_name' => $this->nullIfBlank($validated['landing_name'] ?? null),
            'landing_lat' => $landingLat,
            'landing_lng' => $landingLng,
            'distance_km' => $route['distance_km'],
            'estimated_duration_minutes' => $route['estimated_duration_minutes'],
            'speed_knots' => $speedKnots,
            'route_points' => $route['route_points'],
            'route_profile' => $route['route_profile'],
            'forecast_points' => $this->decodeJson($validated['forecast_points'] ?? null),
            'notes' => $this->nullIfBlank($validated['notes'] ?? null),
        ];
    }

    private function routeWaypointsJson(PlannedSession $plannedSession): string
    {
        if (! is_array($plannedSession->route_profile) || count($plannedSession->route_profile) <= 2) {
            return '';
        }

        return json_encode(
            collect($plannedSession->route_profile)
                ->slice(1, count($plannedSession->route_profile) - 2)
                ->filter(fn (array $point) => isset($point['lat'], $point['lng']))
                ->map(fn (array $point) => [
                    'lat' => round((float) $point['lat'], 6),
                    'lng' => round((float) $point['lng'], 6),
                ])
                ->values()
                ->all(),
        ) ?: '';
    }

    private function buildRouteSummary(
        ?float $launchLat,
        ?float $launchLng,
        ?float $landingLat,
        ?float $landingLng,
        ?string $waypointsJson,
        float $speedKnots,
    ): array {
        $waypoints = collect($this->decodeJson($waypointsJson) ?? [])
            ->filter(fn (mixed $point) => is_array($point) && isset($point['lat'], $point['lng']))
            ->map(fn (array $point) => [
                'lat' => round((float) $point['lat'], 6),
                'lng' => round((float) $point['lng'], 6),
            ])
            ->values();

        $points = collect();

        if ($launchLat !== null && $launchLng !== null) {
            $points->push([
                'lat' => round($launchLat, 6),
                'lng' => round($launchLng, 6),
            ]);
        }

        $points = $points->concat($waypoints);

        if ($landingLat !== null && $landingLng !== null) {
            $points->push([
                'lat' => round($landingLat, 6),
                'lng' => round($landingLng, 6),
            ]);
        }

        $points = $points
            ->filter(fn (array $point, int $index) => $index === 0 || $point !== $points->get($index - 1))
            ->values();

        if ($points->count() < 2) {
            return [
                'distance_km' => 0,
                'estimated_duration_minutes' => null,
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
        $speedKmh = $speedKnots * 1.852;

        $routeProfile = $points->values()->map(function (array $point, int $index) use ($points, &$distanceKm, $padding, $width, $height, $minLat, $latSpan, $minLng, $lngSpan, $speedKmh) {
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
                'minute' => $speedKmh > 0 ? round(($distanceKm / $speedKmh) * 60, 1) : 0.0,
                'distanceKm' => round($distanceKm, 2),
                'speedKmh' => round($speedKmh, 2),
            ];
        })->all();

        return [
            'distance_km' => round($distanceKm, 2),
            'estimated_duration_minutes' => $speedKmh > 0 ? (int) round(($distanceKm / $speedKmh) * 60) : null,
            'route_points' => collect($routeProfile)->map(fn (array $point) => $point['x'].','.$point['y'])->implode(' '),
            'route_profile' => $routeProfile,
        ];
    }

    private function decodeJson(?string $value): ?array
    {
        if (! $value) {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
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

    private function plannedSessionGpx(PlannedSession $plannedSession): ?string
    {
        $points = collect($plannedSession->route_profile ?? [])
            ->filter(fn (mixed $point) => is_array($point) && isset($point['lat'], $point['lng']))
            ->values();

        if ($points->count() < 2) {
            return null;
        }

        $name = e($plannedSession->title ?: 'Planned route');
        $time = ($plannedSession->start_at ?? Carbon::parse($plannedSession->plan_date ?? now(), $plannedSession->timezone ?: 'UTC'))
            ->copy()
            ->utc()
            ->toIso8601String();

        $routePoints = $points
            ->map(function (array $point): string {
                $lat = number_format((float) $point['lat'], 6, '.', '');
                $lng = number_format((float) $point['lng'], 6, '.', '');

                return sprintf('    <rtept lat="%s" lon="%s" />', $lat, $lng);
            })
            ->implode("\n");

        return <<<GPX
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="Your Kayaking Journal" xmlns="http://www.topografix.com/GPX/1/1">
  <metadata>
    <name>{$name}</name>
    <time>{$time}</time>
  </metadata>
  <rte>
    <name>{$name}</name>
{$routePoints}
  </rte>
</gpx>
GPX;
    }
}
