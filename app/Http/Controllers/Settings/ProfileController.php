<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

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
            'status' => $request->session()->get('status'),
            'requiresSetup' => $profile->requiresSetup(),
            'setupMode' => $profile->requiresSetup(),
            'security' => [
                'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
                'twoFactorEnabled' => Features::canManageTwoFactorAuthentication()
                    ? $request->user()->hasEnabledTwoFactorAuthentication()
                    : false,
                'requiresConfirmation' => Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'),
            ],
            'profile' => [
                'name' => $profile->name,
                'email' => $request->user()->email,
                'homeWater' => $profile->home_water,
                'settings' => [
                    'paddlerName' => (string) data_get($settings, 'paddler_name', ''),
                    'kayakClub' => (string) data_get($settings, 'kayak_club', ''),
                    'kayaksOwnedText' => implode(', ', data_get($settings, 'kayaks_owned', [])),
                    'paddlesOwnedText' => implode(', ', data_get($settings, 'paddles_owned', [])),
                    'defaultMapLat' => (string) data_get($settings, 'default_map_view.lat', '64.1670'),
                    'defaultMapLng' => (string) data_get($settings, 'default_map_view.lng', '-21.8210'),
                    'defaultMapZoom' => (string) data_get($settings, 'default_map_view.zoom', '10'),
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
        $wasSetupRequired = $profile->requiresSetup();
        $settings = $profile->settings ?? [];
        $settings['paddler_name'] = $this->blankToNull($validated['paddler_name'] ?? null);
        $settings['kayak_club'] = $this->blankToNull($validated['kayak_club'] ?? null);
        $settings['kayaks_owned'] = $this->explodeManualTags($validated['kayaks_owned_text'] ?? null);
        $settings['paddles_owned'] = $this->explodeManualTags($validated['paddles_owned_text'] ?? null);
        $settings['default_map_view'] = [
            'lat' => round((float) ($validated['default_map_lat'] ?? 64.1670), 6),
            'lng' => round((float) ($validated['default_map_lng'] ?? -21.8210), 6),
            'zoom' => (int) ($validated['default_map_zoom'] ?? 10),
        ];
        $settings['setup_required'] = false;
        $settings['setup_completed_at'] = now()->toIso8601String();
        $profile->settings = $settings;
        $profile->save();

        if ($request->boolean('finish_setup') || $wasSetupRequired) {
            return to_route('dashboard')->with('success', 'Profile setup complete.');
        }

        return to_route('profile.edit')->with('status', 'Profile saved.');
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
