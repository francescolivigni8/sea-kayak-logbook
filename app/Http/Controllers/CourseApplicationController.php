<?php

namespace App\Http\Controllers;

use App\Support\CourseApplicationReportData;
use App\Support\UnitPreferences;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\Request;

class CourseApplicationController extends Controller
{
    public function __construct(
        private readonly CourseApplicationReportData $reportData,
    ) {}

    public function report(Request $request): ViewContract
    {
        $profile = $request->user()->resolveActiveProfile();
        $sessions = $profile->sessions()
            ->with('categories')
            ->latest('session_date')
            ->latest('id')
            ->get();

        return view('courses.report', [
            'report' => $this->reportData->build($profile, $sessions),
            'unitPreferences' => UnitPreferences::fromSettings($profile->settings ?? []),
        ]);
    }
}
