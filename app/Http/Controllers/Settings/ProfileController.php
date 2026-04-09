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
                    'registeredKayaksCount' => (int) data_get($settings, 'registered_kayaks_count', 0),
                    'registeredPaddlesCount' => (int) data_get($settings, 'registered_paddles_count', 0),
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
        $settings['registered_kayaks_count'] = (int) ($validated['registered_kayaks_count'] ?? 0);
        $settings['registered_paddles_count'] = (int) ($validated['registered_paddles_count'] ?? 0);
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
