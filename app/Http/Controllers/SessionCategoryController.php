<?php

namespace App\Http\Controllers;

use App\Models\PaddleSession;
use App\Models\SessionCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SessionCategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $profile = $request->user()->resolveActiveProfile();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $name = trim($validated['name']);
        $slug = Str::slug($name) ?: 'collection';
        $category = $profile->sessionCategories()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $name],
        );

        return back()->with(
            'success',
            $category->wasRecentlyCreated
                ? 'Folder created. Drag logged sessions into it to sort the library.'
                : 'That folder already exists. You can drag sessions into it now.',
        );
    }

    public function attachSession(
        Request $request,
        SessionCategory $sessionCategory,
        PaddleSession $session,
    ): RedirectResponse {
        $profile = $request->user()->resolveActiveProfile();
        $this->ensureCategoryAndSessionBelongToProfile($sessionCategory, $session, $profile->id);

        $sessionCategory->sessions()->syncWithoutDetaching([$session->id]);

        return back()->with('success', "{$session->title} added to {$sessionCategory->name}.");
    }

    public function attachSessions(
        Request $request,
        SessionCategory $sessionCategory,
    ): RedirectResponse {
        $profile = $request->user()->resolveActiveProfile();
        $this->ensureCategoryBelongsToProfile($sessionCategory, $profile->id);

        $validated = $request->validate([
            'session_ids' => ['required', 'array', 'min:1'],
            'session_ids.*' => ['integer'],
        ]);

        $requestedSessionIds = collect($validated['session_ids'])
            ->map(fn (int|string $id) => (int) $id)
            ->unique()
            ->values();
        $sessionIds = PaddleSession::query()
            ->where('profile_id', $profile->id)
            ->whereIn('id', $requestedSessionIds)
            ->pluck('id');

        abort_unless($sessionIds->count() === $requestedSessionIds->count(), 404);

        $sessionCategory->sessions()->syncWithoutDetaching($sessionIds->all());

        $sessionCount = $sessionIds->count();

        return back()->with(
            'success',
            $sessionCount === 1
                ? "1 session added to {$sessionCategory->name}."
                : "{$sessionCount} sessions added to {$sessionCategory->name}.",
        );
    }

    private function ensureCategoryAndSessionBelongToProfile(
        SessionCategory $sessionCategory,
        PaddleSession $session,
        int $profileId,
    ): void {
        $this->ensureCategoryBelongsToProfile($sessionCategory, $profileId);

        abort_unless(
            $session->profile_id === $profileId,
            404,
        );
    }

    private function ensureCategoryBelongsToProfile(
        SessionCategory $sessionCategory,
        int $profileId,
    ): void {
        abort_unless($sessionCategory->profile_id === $profileId, 404);
    }
}
