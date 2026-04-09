<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $journalNav = null;

        if ($request->user()) {
            $profile = $request->user()->resolveActiveProfile();
            $currentYear = now($profile->timezone)->year;
            $baseSessions = $profile->sessions();
            $sessionCount = (clone $baseSessions)->count();
            $topForce = (clone $baseSessions)->max('wind_beaufort');
            $thisYearDistanceKm = round((float) (clone $baseSessions)
                ->whereYear('session_date', $currentYear)
                ->sum('distance_km'), 1);
            $missingMapPointCount = $this->countMissingMapPoints(clone $baseSessions);
            $missingConditionsCount = (clone $baseSessions)
                ->where('conditions_logged', false)
                ->count();
            $missingNotesCount = (clone $baseSessions)
                ->whereRaw("trim(coalesce(notes_public, '')) = ''")
                ->whereRaw("trim(coalesce(notes_private, '')) = ''")
                ->whereRaw("trim(coalesce(expedition_notes, '')) = ''")
                ->count();
            $expeditionMissingMapPointCount = $this->countMissingMapPoints(
                (clone $baseSessions)->where('is_expedition', true),
            );
            $statusItems = [
                [
                    'key' => 'map-points',
                    'label' => 'Map points',
                    'href' => '/sessions',
                    'count' => $missingMapPointCount,
                    'detail' => $missingMapPointCount > 0
                        ? $this->countLabel($missingMapPointCount, 'session still needs a track or pin.', 'sessions still need a track or pin.')
                        : 'All sessions can appear on your maps.',
                    'tone' => 'sea',
                    'active' => $missingMapPointCount > 0,
                ],
                [
                    'key' => 'conditions',
                    'label' => 'Sea conditions',
                    'href' => '/sessions',
                    'count' => $missingConditionsCount,
                    'detail' => $missingConditionsCount > 0
                        ? $this->countLabel($missingConditionsCount, 'session still needs sea conditions.', 'sessions still need sea conditions.')
                        : 'Conditions are logged across the journal.',
                    'tone' => 'sand',
                    'active' => $missingConditionsCount > 0,
                ],
                [
                    'key' => 'notes',
                    'label' => 'Session notes',
                    'href' => '/observations',
                    'count' => $missingNotesCount,
                    'detail' => $missingNotesCount > 0
                        ? $this->countLabel($missingNotesCount, 'session still needs a note.', 'sessions still need notes.')
                        : 'Recent sessions have notes in place.',
                    'tone' => 'violet',
                    'active' => $missingNotesCount > 0,
                ],
                [
                    'key' => 'expedition-pins',
                    'label' => 'Expedition pins',
                    'href' => '/expedition-notes',
                    'count' => $expeditionMissingMapPointCount,
                    'detail' => $expeditionMissingMapPointCount > 0
                        ? $this->countLabel($expeditionMissingMapPointCount, 'expedition still needs a world-map pin.', 'expeditions still need a world-map pin.')
                        : 'Expedition sessions are ready for the world map.',
                    'tone' => 'mint',
                    'active' => $expeditionMissingMapPointCount > 0,
                ],
            ];
            $statusSummaryCount = collect($statusItems)->sum('count');

            $journalNav = [
                'homeWater' => $profile->home_water,
                'publicPath' => '/p/'.$profile->slug,
                'sessionCount' => $sessionCount,
                'topForce' => $topForce ?: null,
                'thisYearDistanceKm' => $thisYearDistanceKm,
                'statusItems' => $statusItems,
                'statusSummary' => $statusSummaryCount > 0
                    ? $this->countLabel($statusSummaryCount, 'attention point to catch up across sessions and expeditions.', 'attention points to catch up across sessions and expeditions.')
                    : 'Everything is caught up across routes, conditions, and expedition mapping.',
            ];
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'journalNav' => $journalNav,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    private function countMissingMapPoints(mixed $query): int
    {
        return $query
            ->whereNull('gpx_path')
            ->whereNull('fit_path')
            ->whereNull('route_profile')
            ->where(function ($locationQuery) {
                $locationQuery
                    ->where(function ($launchQuery) {
                        $launchQuery
                            ->whereNull('launch_lat')
                            ->orWhereNull('launch_lng');
                    })
                    ->where(function ($landingQuery) {
                        $landingQuery
                            ->whereNull('landing_lat')
                            ->orWhereNull('landing_lng');
                    });
            })
            ->count();
    }

    private function countLabel(int $count, string $singular, string $plural): string
    {
        return $count.' '.($count === 1 ? $singular : $plural);
    }
}
