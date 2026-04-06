<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Support\ProfileDashboardData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpeditionPlaceController extends Controller
{
    public function __construct(
        private readonly ProfileDashboardData $dashboardData,
    ) {}

    public function index(Request $request): Response
    {
        $profile = $request->user()->resolveActiveProfile();
        $sessions = $profile->sessions()
            ->latest('session_date')
            ->latest('id')
            ->get();

        return Inertia::render('expeditions/Index', $this->dashboardData->buildExpeditionAtlas($profile, $sessions));
    }

    public function show(Request $request, string $place): Response
    {
        $profile = $request->user()->resolveActiveProfile();
        $sessions = $profile->sessions()
            ->latest('session_date')
            ->latest('id')
            ->get();

        $payload = $this->dashboardData->buildExpeditionPlacePage($profile, $sessions, $place);

        abort_unless($payload, 404);

        return Inertia::render('expeditions/Show', $payload);
    }

    public function publicShow(Profile $profile, string $place): Response
    {
        abort_unless($profile->is_public, 404);

        $sessions = $profile->sessions()
            ->where('is_public', true)
            ->latest('session_date')
            ->latest('id')
            ->get();

        $payload = $this->dashboardData->buildExpeditionPlacePage($profile, $sessions, $place, true);

        abort_unless($payload, 404);

        return Inertia::render('profiles/ExpeditionShow', $payload);
    }

    public function publicIndex(Profile $profile): Response
    {
        abort_unless($profile->is_public, 404);

        $sessions = $profile->sessions()
            ->where('is_public', true)
            ->latest('session_date')
            ->latest('id')
            ->get();

        return Inertia::render('profiles/ExpeditionIndex', $this->dashboardData->buildExpeditionAtlas($profile, $sessions, true));
    }
}
