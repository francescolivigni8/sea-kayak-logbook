<?php

namespace App\Http\Controllers;

use App\Support\DashboardPreferences;
use App\Support\ProfileDashboardData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ProfileDashboardData $dashboardData,
    ) {}

    public function __invoke(Request $request): Response
    {
        $profile = $request->user()->resolveActiveProfile();
        $sessions = $profile->sessions()
            ->latest('session_date')
            ->latest('id')
            ->get();

        return Inertia::render('Dashboard', [
            ...$this->dashboardData->build($profile, $sessions),
            'dashboardPreferences' => DashboardPreferences::fromSettings($profile->settings ?? []),
        ]);
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $profile = $request->user()->resolveActiveProfile();
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['string'],
            'hidden' => ['nullable', 'array'],
            'hidden.*' => ['string'],
        ]);

        $settings = $profile->settings ?? [];
        $settings['dashboard_layout'] = DashboardPreferences::sanitize([
            'order' => $validated['order'],
            'hidden' => $validated['hidden'] ?? [],
        ]);
        $profile->settings = $settings;
        $profile->save();

        return to_route('dashboard')->with('success', 'Dashboard layout saved.');
    }
}
