<?php

namespace App\Http\Middleware;

use App\Models\PaddleSession;
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
            $allSessions = (clone $baseSessions)->get(['distance_km', 'wind_beaufort', 'session_date']);
            $kayaksOwned = data_get($profile->settings, 'kayaks_owned', data_get($profile->settings, 'registered_kayaks', []));
            $paddlesOwned = data_get($profile->settings, 'paddles_owned', data_get($profile->settings, 'registered_paddles', []));

            $journalNav = [
                'homeWater' => $profile->home_water,
                'publicPath' => '/p/'.$profile->slug,
                'sessionCount' => $allSessions->count(),
                'topForce' => $allSessions->max(fn (PaddleSession $session) => $session->wind_beaufort ?? 0) ?: null,
                'thisYearDistanceKm' => round((float) $allSessions
                    ->filter(fn (PaddleSession $session) => $session->session_date?->year === $currentYear)
                    ->sum('distance_km'), 1),
                'paddlerCard' => [
                    'name' => data_get($profile->settings, 'paddler_name', $profile->name),
                    'club' => data_get($profile->settings, 'kayak_club', 'Independent'),
                    'kayaksOwned' => is_array($kayaksOwned) ? array_values(array_filter($kayaksOwned)) : [],
                    'paddlesOwned' => is_array($paddlesOwned) ? array_values(array_filter($paddlesOwned)) : [],
                ],
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
}
