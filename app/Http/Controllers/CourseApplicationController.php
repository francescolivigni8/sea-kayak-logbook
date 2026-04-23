<?php

namespace App\Http\Controllers;

use App\Support\CourseApplicationReportData;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourseApplicationController extends Controller
{
    public function __construct(
        private readonly CourseApplicationReportData $reportData,
    ) {}

    public function index(Request $request): Response
    {
        $profile = $request->user()->resolveActiveProfile();
        $sessions = $profile->sessions()
            ->with('categories')
            ->latest('session_date')
            ->latest('id')
            ->get();

        return Inertia::render('courses/Index', [
            'report' => $this->reportData->build($profile, $sessions),
        ]);
    }

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
        ]);
    }
}
