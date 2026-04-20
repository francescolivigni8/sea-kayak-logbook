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

    private function ensureCategoryAndSessionBelongToProfile(
        SessionCategory $sessionCategory,
        PaddleSession $session,
        int $profileId,
    ): void {
        abort_unless(
            $sessionCategory->profile_id === $profileId && $session->profile_id === $profileId,
            404,
        );
    }
}
