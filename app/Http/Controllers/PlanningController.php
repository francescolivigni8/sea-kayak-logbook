<?php

namespace App\Http\Controllers;

use App\Models\PaddleSession;
use App\Models\Profile;
use App\Support\StormglassWeatherService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlanningController extends Controller
{
    public function __construct(
        private readonly StormglassWeatherService $stormglassWeather,
    ) {}

    public function index(Request $request): Response
    {
        $profile = $request->user()->resolveActiveProfile();

        return Inertia::render('planning/Index', [
            'profile' => $this->mapProfile($profile),
            'weatherAutofillAvailable' => $this->stormglassWeather->isConfigured(),
            'formDefaults' => [
                'plan_date' => now($profile->timezone)->toDateString(),
                'start_time_local' => '09:00',
                'speed_knots' => '3.5',
            ],
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
        ]);

        $previewSession = new PaddleSession([
            'session_date' => $validated['plan_date'],
            'start_at' => $this->buildStartAt($validated['plan_date'], $validated['start_time_local'] ?? null, $profile->timezone),
            'timezone' => $profile->timezone,
            'launch_lat' => $this->nullableFloat($validated['lat'] ?? null),
            'launch_lng' => $this->nullableFloat($validated['lng'] ?? null),
        ]);

        $result = $this->stormglassWeather->previewSession($previewSession);

        return response()->json([
            ...$result,
            'point' => [
                'label' => $validated['label'] ?? 'Waypoint',
                'lat' => $this->nullableFloat($validated['lat'] ?? null),
                'lng' => $this->nullableFloat($validated['lng'] ?? null),
            ],
            'message' => match ($result['status']) {
                'filled' => sprintf('Forecast filled %d fields for %s.', (int) ($result['filledFields'] ?? 0), $validated['label'] ?? 'waypoint'),
                'failed' => $result['reason'] ?? 'Forecast preview failed.',
                default => $result['reason'] ?? 'Forecast could not fill this waypoint yet.',
            },
        ]);
    }

    private function mapProfile(Profile $profile): array
    {
        return [
            'name' => $profile->name,
            'slug' => $profile->slug,
            'homeWater' => $profile->home_water,
            'timezone' => $profile->timezone,
            'defaultMapView' => $this->defaultMapView($profile),
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

    private function nullableFloat(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }
}
