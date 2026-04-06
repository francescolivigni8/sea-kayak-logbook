<?php

namespace App\Http\Controllers;

use App\Support\ProfileDashboardData;
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

        return Inertia::render('Dashboard', $this->dashboardData->build($profile, $sessions));
    }
}
