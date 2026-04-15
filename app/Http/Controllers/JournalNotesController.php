<?php

namespace App\Http\Controllers;

use App\Models\PaddleSession;
use App\Models\Profile;
use App\Support\SessionMediaService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JournalNotesController extends Controller
{
    public function __construct(
        private readonly SessionMediaService $media,
    ) {}

    public function observations(Request $request): Response
    {
        $profile = $request->user()->resolveActiveProfile();
        $sessions = $profile->sessions()
            ->latest('session_date')
            ->latest('id')
            ->get()
            ->filter(fn (PaddleSession $session) => filled($session->notes_public))
            ->values();

        return Inertia::render('notes/Index', [
            'profile' => $this->mapProfile($profile),
            'mode' => 'observations',
            'title' => 'Session notes',
            'description' => 'A diary-style wall of session observations, quick learnings, and short reflections without opening every paddle one by one.',
            'count' => $sessions->count(),
            'items' => $sessions->map(fn (PaddleSession $session) => $this->mapItem($session, false))->values(),
        ]);
    }

    public function expeditionNotes(Request $request): Response
    {
        $profile = $request->user()->resolveActiveProfile();
        $sessions = $profile->sessions()
            ->latest('session_date')
            ->latest('id')
            ->get()
            ->filter(fn (PaddleSession $session) => filled($session->expedition_notes) || $session->is_expedition)
            ->values();

        return Inertia::render('notes/Index', [
            'profile' => $this->mapProfile($profile),
            'mode' => 'expedition-notes',
            'title' => 'Expedition notes',
            'description' => 'Field learnings about food, gear, camp rhythm, and what to repeat or change on the next multiday journey.',
            'count' => $sessions->count(),
            'items' => $sessions->map(fn (PaddleSession $session) => $this->mapItem($session, true))->values(),
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

    private function mapItem(PaddleSession $session, bool $expeditionMode): array
    {
        $primaryNote = $expeditionMode
            ? ($session->expedition_notes ?: $session->notes_public ?: $session->notes_private)
            : $session->notes_public;

        return [
            'id' => $session->id,
            'title' => $session->title,
            'date' => $session->session_date?->format('l, j F Y'),
            'category' => $this->routeCategoryLabel($session->route_category),
            'beaufort' => $session->wind_beaufort,
            'launchName' => $session->launch_name,
            'summary' => $primaryNote ?: 'No note text saved yet.',
            'chips' => array_values(array_filter([
                $session->launch_name,
                ...array_slice($session->route_tags ?? [], 0, 4),
                $session->is_expedition ? 'expedition' : null,
            ])),
            'photoUrl' => $this->media->url($session->session_photo_path),
            'path' => route('sessions.show', $session),
        ];
    }

    private function routeCategoryLabel(?string $category): string
    {
        return match ($category) {
            'training' => 'Training',
            'benchmark' => 'Benchmark',
            'navigation' => 'Navigation',
            'rescue-practice' => 'Rescue',
            'expedition' => 'Expedition',
            default => 'Journey',
        };
    }
}
