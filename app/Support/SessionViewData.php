<?php

namespace App\Support;

use App\Models\PaddleSession;
use App\Models\PlannedSession;
use App\Models\Profile;
use Illuminate\Support\Str;

class SessionViewData
{
    public function __construct(
        private readonly SessionFolderService $folders,
        private readonly SessionMediaService $media,
        private readonly ProfileViewData $profiles,
        private readonly RouteCategoryLabeler $routeCategories,
    ) {}

    public function profile(Profile $profile): array
    {
        return [
            ...$this->profiles->planning($profile),
            'kayaksOwned' => data_get($profile->settings, 'kayaks_owned', []),
            'paddlesOwned' => data_get($profile->settings, 'paddles_owned', []),
            'folderNames' => $this->folders->folderNames($profile),
        ];
    }

    public function sessionListItem(PaddleSession $session): array
    {
        return [
            'id' => $session->id,
            'title' => $session->title,
            'date' => optional($session->session_date)->toDateString(),
            'launchName' => $session->launch_name,
            'distanceKm' => (float) $session->distance_km,
            'durationMinutes' => (int) $session->duration_minutes,
            'beaufort' => $session->wind_beaufort,
            'routeCategoryLabel' => $this->routeCategories->standard($session->route_category),
            'isExpedition' => (bool) $session->is_expedition,
            'expeditionDays' => $session->expedition_days,
            'hasTrack' => $this->hasTrackData($session),
            'hasObservation' => filled($session->notes_public),
            'photoUrl' => $this->media->url($session->session_photo_path),
            'folders' => $session->categories
                ->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ])
                ->values(),
        ];
    }

    public function plannedSessionListItem(PlannedSession $plannedSession): array
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

    public function sessionDetail(PaddleSession $session): array
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
            'routeCategoryLabel' => $this->routeCategories->standard($session->route_category),
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
            'folders' => $session->categories
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

    public function formDefaults(Profile $profile, ?PaddleSession $session = null): array
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

    public function quickEntryMemory(Profile $profile, ?PaddleSession $session = null): array
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

    private function hasTrackData(PaddleSession $session): bool
    {
        return filled($session->gpx_path)
            || filled($session->fit_path)
            || (is_array($session->route_profile) && count($session->route_profile) > 1);
    }
}
