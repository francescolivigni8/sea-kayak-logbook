<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportGarminHistoryRequest;
use App\Models\Profile;
use App\Support\GarminImportService;
use App\Support\StormglassWeatherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class GarminImportController extends Controller
{
    public function __construct(
        private readonly StormglassWeatherService $stormglassWeather,
    ) {}

    public function create(Request $request): Response
    {
        $profile = $request->user()->resolveActiveProfile();

        return Inertia::render('imports/Garmin', [
            'profile' => $this->mapProfile($profile),
            'weatherAutofillAvailable' => $this->stormglassWeather->isConfigured(),
            'stats' => [
                'sessionCount' => $profile->sessions()->count(),
                'distanceKm' => round((float) $profile->sessions()->sum('distance_km'), 1),
                'trackSessions' => $profile->sessions()
                    ->get()
                    ->filter(fn ($session) => filled($session->gpx_path) || filled($session->fit_path) || (is_array($session->route_profile) && count($session->route_profile) > 1))
                    ->count(),
                'fitSessions' => $profile->sessions()->whereNotNull('fit_path')->count(),
            ],
        ]);
    }

    public function store(ImportGarminHistoryRequest $request, GarminImportService $importService): RedirectResponse
    {
        $profile = $request->user()->resolveActiveProfile();
        $disk = Storage::disk('local');
        $baseDirectory = 'imports/'.$profile->slug.'/'.Str::uuid();
        $csvPath = null;

        try {
            $csvFile = $request->file('csv_file');
            $csvPath = $csvFile->storeAs($baseDirectory, $csvFile->getClientOriginalName(), 'local');

            $gpxDirectory = null;
            foreach ($request->file('gpx_files', []) as $file) {
                $gpxDirectory ??= $baseDirectory.'/gpx';
                $file->storeAs($gpxDirectory, $file->getClientOriginalName(), 'local');
            }

            $fitDirectory = null;
            foreach ($request->file('fit_files', []) as $file) {
                $fitDirectory ??= $baseDirectory.'/fit';
                $file->storeAs($fitDirectory, $file->getClientOriginalName(), 'local');
            }

            $summary = $importService->import(
                $profile,
                $disk->path($csvPath),
                $gpxDirectory ? $disk->path($gpxDirectory) : null,
                $fitDirectory ? $disk->path($fitDirectory) : null,
                $request->boolean('autofill_weather'),
            );
        } finally {
            $disk->deleteDirectory($baseDirectory);
        }

        return redirect()
            ->route('sessions.index')
            ->with('success', sprintf(
                'Garmin import finished: %d sessions, %s km, %d GPX matched, %d FIT matched.%s Review imported sessions and add observations where useful.',
                $summary['imported'],
                number_format($summary['distanceKm'], 1),
                $summary['gpxMatched'],
                $summary['fitMatched'],
                $request->boolean('autofill_weather')
                    ? sprintf(' Stormglass weather filled %d sessions, skipped %d, failed %d.', $summary['weatherFilled'], $summary['weatherSkipped'], $summary['weatherFailed'])
                    : ''
            ));
    }

    private function mapProfile(Profile $profile): array
    {
        return [
            'name' => $profile->name,
            'homeWater' => $profile->home_water,
            'timezone' => $profile->timezone,
        ];
    }
}
