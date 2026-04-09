<?php

namespace App\Http\Controllers;

use App\Models\PaddleSession;
use App\Models\Profile;
use App\Support\SessionMediaService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DiaryController extends Controller
{
    public function __construct(
        private readonly SessionMediaService $media,
    ) {}

    public function __invoke(Request $request): Response
    {
        $profile = $request->user()->resolveActiveProfile();
        $sessions = $profile->sessions()
            ->latest('session_date')
            ->latest('id')
            ->get();

        return Inertia::render('diary/Index', [
            'profile' => $this->mapProfile($profile),
            'stats' => [
                'sessionCount' => $sessions->count(),
                'paddledDays' => $sessions
                    ->filter(fn (PaddleSession $session) => $session->session_date !== null)
                    ->map(fn (PaddleSession $session) => $session->session_date->toDateString())
                    ->unique()
                    ->count(),
                'distanceKm' => round((float) $sessions->sum('distance_km'), 1),
                'expeditionTrips' => (int) $sessions->where('is_expedition', true)->count(),
            ],
            'entries' => $sessions
                ->map(fn (PaddleSession $session) => $this->mapDiaryEntry($session))
                ->values(),
        ]);
    }

    private function mapProfile(Profile $profile): array
    {
        return [
            'name' => $profile->name,
            'slug' => $profile->slug,
            'homeWater' => $profile->home_water,
            'timezone' => $profile->timezone,
        ];
    }

    private function mapDiaryEntry(PaddleSession $session): array
    {
        return [
            'id' => $session->id,
            'title' => $session->title,
            'date' => $session->session_date?->toDateString(),
            'dateLabel' => $session->session_date?->format('d M Y'),
            'launchName' => $session->launch_name,
            'distanceKm' => round((float) $session->distance_km, 1),
            'durationMinutes' => (int) $session->duration_minutes,
            'beaufort' => $session->wind_beaufort,
            'routeCategoryLabel' => $this->routeCategoryLabel($session->route_category),
            'isExpedition' => (bool) $session->is_expedition,
            'expeditionDays' => $session->expedition_days,
            'hasTrack' => $this->hasTrackData($session),
            'photoUrl' => $this->media->url($session->session_photo_path),
            'notesPreview' => $session->expedition_notes ?: $session->notes_public ?: $session->notes_private,
            'weatherSummary' => $session->weather_summary,
            'path' => route('sessions.show', $session),
        ];
    }

    private function routeCategoryLabel(?string $category): string
    {
        return match ($category) {
            'benchmark' => 'Benchmark',
            'training' => 'Training',
            'journey' => 'Journey',
            'navigation' => 'Navigation',
            'rescue-practice' => 'Rescue practice',
            'expedition' => 'Expedition',
            default => ucfirst(str_replace('-', ' ', (string) $category)),
        };
    }

    private function hasTrackData(PaddleSession $session): bool
    {
        return filled($session->gpx_path)
            || filled($session->fit_path)
            || (is_array($session->route_profile) && count($session->route_profile) > 1);
    }
}
