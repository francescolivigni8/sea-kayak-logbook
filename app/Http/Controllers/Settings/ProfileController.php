<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Support\Arr;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $profile = $request->user()->resolveActiveProfile();
        $settings = $profile->settings ?? [];

        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'profile' => [
                'name' => $profile->name,
                'homeWater' => $profile->home_water,
                'settings' => [
                    'paddlerName' => (string) data_get($settings, 'paddler_name', ''),
                    'kayakClub' => (string) data_get($settings, 'kayak_club', ''),
                    'kayaksOwnedText' => implode(', ', data_get($settings, 'kayaks_owned', [])),
                    'paddlesOwnedText' => implode(', ', data_get($settings, 'paddles_owned', [])),
                    'bio' => (string) data_get($settings, 'bio', ''),
                ],
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $request->user()->fill(Arr::only($validated, ['name', 'email']));

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        $profile = $request->user()->resolveActiveProfile();
        $settings = $profile->settings ?? [];
        $settings['paddler_name'] = $this->blankToNull($validated['paddler_name'] ?? null);
        $settings['kayak_club'] = $this->blankToNull($validated['kayak_club'] ?? null);
        $settings['kayaks_owned'] = $this->explodeManualTags($validated['kayaks_owned_text'] ?? null);
        $settings['paddles_owned'] = $this->explodeManualTags($validated['paddles_owned_text'] ?? null);
        $settings['bio'] = $this->blankToNull($validated['bio'] ?? null);
        $profile->settings = $settings;
        $profile->save();

        return to_route('profile.edit');
    }

    private function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function explodeManualTags(?string $value): array
    {
        if ($value === null) {
            return [];
        }

        return collect(preg_split('/[\n,]+/', $value) ?: [])
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
