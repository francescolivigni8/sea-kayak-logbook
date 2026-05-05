<?php

namespace App\Support;

use App\Models\PaddleSession;
use App\Models\Profile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CourseApplicationReportData
{
    public function __construct(
        private readonly ProfileDashboardData $dashboardData,
        private readonly RouteCategoryLabeler $routeCategories,
    ) {}

    public function build(Profile $profile, Collection $sessions): array
    {
        $sessions = $sessions->values();
        $dashboard = $this->dashboardData->build($profile, $sessions);
        $settings = $profile->settings ?? [];

        $observationCount = (int) $sessions
            ->filter(fn (PaddleSession $session) => filled($session->notes_public) || filled($session->expedition_notes))
            ->count();
        $skillsSummary = $this->summarizeStringLists($sessions, fn (PaddleSession $session) => $session->skills ?? []);
        $folderSummary = $sessions
            ->flatMap(fn (PaddleSession $session) => $session->categories->pluck('name'))
            ->countBy()
            ->sortDesc()
            ->take(10)
            ->map(fn (int $count, string $name) => [
                'label' => $name,
                'count' => $count,
            ])
            ->values()
            ->all();

        return [
            'generatedAt' => now($profile->timezone)->format('d M Y H:i'),
            'purpose' => 'Advanced sea kayak course application',
            'profile' => [
                'name' => $profile->name,
                'paddlerName' => (string) data_get($settings, 'paddler_name', $profile->name),
                'homeWater' => $profile->home_water,
                'timezone' => $profile->timezone,
                'kayakClub' => data_get($settings, 'kayak_club'),
                'kayaksOwned' => array_values(data_get($settings, 'kayaks_owned', [])),
                'paddlesOwned' => array_values(data_get($settings, 'paddles_owned', [])),
            ],
            'headline' => $dashboard['headline'],
            'yearSnapshots' => $dashboard['yearSnapshots'],
            'monthlyDistance' => $dashboard['monthlyDistance'],
            'routeMix' => $dashboard['routeMix'],
            'seaState' => $dashboard['seaState'],
            'expeditionSummary' => $dashboard['expeditionSummary'],
            'recentSessions' => $dashboard['recentSessions'],
            'observationCount' => $observationCount,
            'skillsSummary' => $skillsSummary,
            'folderSummary' => $folderSummary,
            'evidenceSessions' => $this->buildEvidenceSessions($sessions),
            'noteExcerpts' => $this->buildNoteExcerpts($sessions),
            'sessionLog' => $this->buildSessionLog($sessions),
        ];
    }

    /**
     * @param  callable(PaddleSession): array<int, string>  $resolver
     * @return array<int, array{label:string,count:int}>
     */
    private function summarizeStringLists(Collection $sessions, callable $resolver): array
    {
        return $sessions
            ->flatMap(function (PaddleSession $session) use ($resolver) {
                return collect($resolver($session))
                    ->map(fn (string $value) => trim($value))
                    ->filter();
            })
            ->countBy()
            ->sortDesc()
            ->take(12)
            ->map(fn (int $count, string $label) => [
                'label' => $label,
                'count' => $count,
            ])
            ->values()
            ->all();
    }

    private function buildEvidenceSessions(Collection $sessions): array
    {
        return $sessions
            ->sortByDesc(fn (PaddleSession $session) => ($this->evidenceScore($session) * 1000000) + ($session->session_date?->getTimestamp() ?? 0))
            ->map(function (PaddleSession $session) {
                return [
                    'id' => $session->id,
                    'date' => $session->session_date?->format('d M Y'),
                    'title' => $session->title,
                    'routeCategoryLabel' => $this->routeCategories->standard($session->route_category),
                    'distanceKm' => round((float) $session->distance_km, 1),
                    'durationMinutes' => (int) $session->duration_minutes,
                    'beaufort' => $session->wind_beaufort,
                    'isExpedition' => (bool) $session->is_expedition,
                    'launchName' => $session->launch_name,
                    'path' => route('sessions.show', $session),
                    'summary' => $this->excerpt(
                        $session->notes_public
                            ?: $session->expedition_notes
                            ?: $session->route_summary
                            ?: $session->weather_summary
                    ),
                    'evidenceTags' => array_values(array_filter([
                        $session->is_expedition ? 'Expedition' : null,
                        $this->hasTrackData($session) ? 'Track attached' : null,
                        $session->conditions_logged ? 'Conditions logged' : null,
                        $session->development_logged ? 'Development logged' : null,
                        filled($session->notes_public) ? 'Observation logged' : null,
                        filled($session->expedition_notes) ? 'Expedition notes' : null,
                    ])),
                    'score' => round($this->evidenceScore($session), 1),
                ];
            })
            ->take(8)
            ->values()
            ->all();
    }

    private function buildNoteExcerpts(Collection $sessions): array
    {
        return $sessions
            ->filter(fn (PaddleSession $session) => filled($session->notes_public) || filled($session->expedition_notes) || filled($session->what_went_well) || filled($session->improve_next))
            ->sortByDesc(fn (PaddleSession $session) => $session->session_date?->toDateString() ?? '')
            ->take(8)
            ->map(function (PaddleSession $session) {
                $body = $session->notes_public
                    ?: $session->expedition_notes
                    ?: $session->what_went_well
                    ?: $session->improve_next;

                return [
                    'id' => $session->id,
                    'date' => $session->session_date?->format('d M Y'),
                    'title' => $session->title,
                    'source' => filled($session->notes_public)
                        ? 'Observation'
                        : (filled($session->expedition_notes) ? 'Expedition notes' : (filled($session->what_went_well) ? 'What went well' : 'Improve next')),
                    'excerpt' => $this->excerpt($body, 210),
                    'path' => route('sessions.show', $session),
                ];
            })
            ->values()
            ->all();
    }

    private function buildSessionLog(Collection $sessions): array
    {
        return $sessions
            ->sortByDesc(fn (PaddleSession $session) => $session->session_date?->toDateString() ?? '')
            ->values()
            ->map(function (PaddleSession $session) {
                return [
                    'id' => $session->id,
                    'date' => $session->session_date?->format('d M Y'),
                    'title' => $session->title,
                    'launchName' => $session->launch_name,
                    'routeCategoryLabel' => $this->routeCategories->standard($session->route_category),
                    'distanceKm' => round((float) $session->distance_km, 1),
                    'durationMinutes' => (int) $session->duration_minutes,
                    'beaufort' => $session->wind_beaufort,
                    'isExpedition' => (bool) $session->is_expedition,
                    'hasTrack' => $this->hasTrackData($session),
                    'hasObservation' => filled($session->notes_public) || filled($session->expedition_notes),
                    'skills' => collect($session->skills ?? [])->filter()->values()->all(),
                    'folders' => $session->categories->pluck('name')->values()->all(),
                    'noteExcerpt' => $this->excerpt(
                        $session->notes_public
                            ?: $session->expedition_notes
                            ?: $session->route_summary,
                        100,
                    ),
                ];
            })
            ->all();
    }

    private function hasTrackData(PaddleSession $session): bool
    {
        return filled($session->gpx_path)
            || filled($session->fit_path)
            || (is_array($session->route_profile) && count($session->route_profile) > 1);
    }

    private function evidenceScore(PaddleSession $session): float
    {
        $score = (float) $session->distance_km;
        $score += min(((int) $session->duration_minutes) / 45, 8);
        $score += ((int) ($session->wind_beaufort ?? 0)) * 3;
        $score += $session->is_expedition ? 10 : 0;
        $score += ((int) ($session->expedition_days ?? 0)) * 2;
        $score += $session->conditions_logged ? 3 : 0;
        $score += $session->development_logged ? 3 : 0;
        $score += $this->hasTrackData($session) ? 4 : 0;
        $score += filled($session->notes_public) || filled($session->expedition_notes) ? 3 : 0;

        return $score;
    }

    private function excerpt(?string $value, int $limit = 160): ?string
    {
        if (! filled($value)) {
            return null;
        }

        return Str::limit(trim((string) $value), $limit);
    }
}
