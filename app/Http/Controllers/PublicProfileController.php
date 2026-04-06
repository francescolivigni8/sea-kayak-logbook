<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Support\ProfileDashboardData;
use Inertia\Inertia;
use Inertia\Response;

class PublicProfileController extends Controller
{
    public function __construct(
        private readonly ProfileDashboardData $dashboardData,
    ) {}

    public function __invoke(Profile $profile): Response
    {
        abort_unless($profile->is_public, 404);

        $sessions = $profile->sessions()
            ->where('is_public', true)
            ->latest('session_date')
            ->latest('id')
            ->get();

        return Inertia::render('profiles/PublicShow', $this->dashboardData->build($profile, $sessions, true));
    }
}
