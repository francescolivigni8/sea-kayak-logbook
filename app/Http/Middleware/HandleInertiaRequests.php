<?php

namespace App\Http\Middleware;

use App\Support\UnitPreferences;
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
        $unitPreferences = UnitPreferences::defaults();

        if ($request->user()) {
            $profile = $request->user()->resolveActiveProfile();
            $unitPreferences = UnitPreferences::fromSettings($profile->settings ?? []);
            $currentYear = now($profile->timezone)->year;
            $baseSessions = $profile->sessions();
            $sessionCount = (clone $baseSessions)->count();
            $topForce = (clone $baseSessions)->max('wind_beaufort');
            $thisYearDistanceKm = round((float) (clone $baseSessions)
                ->whereYear('session_date', $currentYear)
                ->sum('distance_km'), 1);

            $journalNav = [
                'homeWater' => $profile->home_water,
                'sessionCount' => $sessionCount,
                'topForce' => $topForce ?: null,
                'thisYearDistanceKm' => $thisYearDistanceKm,
            ];
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'legal' => [
                'productName' => config('kayak.legal.product_name'),
                'copyrightOwner' => config('kayak.legal.copyright_owner'),
            ],
            'auth' => [
                'user' => $request->user(),
            ],
            'journalNav' => $journalNav,
            'ownerTools' => [
                'canViewUsers' => $request->user()?->canViewOwnerTools() ?? false,
            ],
            'unitPreferences' => $unitPreferences,
            'integrations' => [
                'maps' => [
                    'provider' => config('services.maps.provider'),
                    'maptilerKey' => config('services.maps.maptiler_key'),
                    'weatherEnabled' => (bool) config('services.maps.weather_enabled'),
                    'styles' => config('services.maps.styles'),
                ],
                'analytics' => [
                    'posthog' => [
                        'enabled' => (bool) config('services.posthog.enabled') && filled(config('services.posthog.key')),
                        'key' => config('services.posthog.key'),
                        'host' => config('services.posthog.host'),
                    ],
                ],
                'monitoring' => [
                    'sentry' => [
                        'enabled' => filled(config('services.sentry.frontend_dsn')),
                        'dsn' => config('services.sentry.frontend_dsn'),
                        'environment' => config('services.sentry.environment'),
                    ],
                ],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
