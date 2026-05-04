<?php

namespace App\Support;

use App\Models\PaddleSession;
use App\Models\Profile;
use App\Models\SessionCategory;
use Illuminate\Support\Str;

class SessionFolderService
{
    public function folderCount(Profile $profile): int
    {
        return $profile->sessionCategories()->count();
    }

    public function folderNames(Profile $profile): array
    {
        return $profile->sessionCategories()
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->all();
    }

    public function mapFolderGroups(Profile $profile): array
    {
        return $profile->sessionCategories()
            ->with(['sessions' => fn ($query) => $query
                ->latest('session_date')
                ->latest('paddle_sessions.id')])
            ->orderBy('name')
            ->get()
            ->map(function (SessionCategory $folder) {
                $sessions = $folder->sessions;

                return [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'slug' => $folder->slug,
                    'sessionCount' => $sessions->count(),
                    'distanceKm' => round((float) $sessions->sum('distance_km'), 1),
                    'latestDate' => optional($sessions->first()?->session_date)->toDateString(),
                    'sessions' => $sessions
                        ->take(4)
                        ->map(fn (PaddleSession $session) => [
                            'id' => $session->id,
                            'title' => $session->title,
                            'date' => optional($session->session_date)->toDateString(),
                        ])
                        ->values(),
                ];
            })
            ->values()
            ->all();
    }

    public function syncSessionFolders(PaddleSession $session, Profile $profile, ?string $namesText): void
    {
        $folderIds = collect($this->folderNamesFromText($namesText))
            ->map(fn (string $name) => $this->firstOrCreateFolder($profile, $name)->id)
            ->values()
            ->all();

        $session->categories()->sync($folderIds);
    }

    public function firstOrCreateFolder(Profile $profile, string $name): SessionCategory
    {
        $slug = Str::slug($name) ?: 'folder';

        return $profile->sessionCategories()->firstOrCreate(
            ['slug' => $slug],
            ['name' => trim($name)],
        );
    }

    private function folderNamesFromText(?string $value): array
    {
        if (! $value) {
            return [];
        }

        return collect(preg_split('/[,;\n]+/', $value) ?: [])
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique(fn (string $name) => Str::lower($name))
            ->take(12)
            ->values()
            ->all();
    }
}
