<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\PaddleSession;
use App\Support\SessionMediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                'reportUrl' => route('profile.report'),
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
            $request->user()->email_verified_at = Features::enabled(Features::emailVerification())
                ? null
                : now();
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

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $ownedProfiles = $user->ownedProfiles()
            ->with([
                'memberships',
                'sessions.categories',
                'plannedSessions',
                'sessionCategories.sessions:id,title,session_date,distance_km',
            ])
            ->get();

        $payload = [
            'app' => config('app.name', 'Your Kayaking Journal'),
            'exported_at' => now()->toIso8601String(),
            'scope' => 'Owned journal data for the signed-in account.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
            ],
            'profile_memberships' => $user->profileMemberships()
                ->with('profile:id,name,slug')
                ->get()
                ->map(fn ($membership) => [
                    'profile' => $membership->profile?->only(['id', 'name', 'slug']),
                    'role' => $membership->role,
                    'created_at' => $membership->created_at?->toIso8601String(),
                ])
                ->values(),
            'profiles' => $ownedProfiles
                ->map(fn ($profile) => [
                    'id' => $profile->id,
                    'slug' => $profile->slug,
                    'name' => $profile->name,
                    'home_water' => $profile->home_water,
                    'timezone' => $profile->timezone,
                    'default_map_style' => $profile->default_map_style,
                    'is_public' => (bool) $profile->is_public,
                    'settings' => $profile->settings,
                    'created_at' => $profile->created_at?->toIso8601String(),
                    'updated_at' => $profile->updated_at?->toIso8601String(),
                    'memberships' => $profile->memberships
                        ->map(fn ($membership) => [
                            'user_id' => $membership->user_id,
                            'role' => $membership->role,
                            'created_at' => $membership->created_at?->toIso8601String(),
                        ])
                        ->values(),
                    'session_categories' => $profile->sessionCategories
                        ->map(fn ($category) => [
                            'id' => $category->id,
                            'name' => $category->name,
                            'slug' => $category->slug,
                            'color' => $category->color,
                            'description' => $category->description,
                            'sessions' => $category->sessions
                                ->map(fn ($session) => [
                                    'id' => $session->id,
                                    'title' => $session->title,
                                    'session_date' => $session->session_date?->toDateString(),
                                    'distance_km' => $session->distance_km,
                                ])
                                ->values(),
                        ])
                        ->values(),
                    'sessions' => $profile->sessions
                        ->map(fn ($session) => [
                            ...$session->makeHidden(['profile'])->toArray(),
                            'categories' => $session->categories
                                ->map(fn ($category) => $category->only(['id', 'name', 'slug']))
                                ->values(),
                        ])
                        ->values(),
                    'planned_sessions' => $profile->plannedSessions
                        ->map(fn ($plannedSession) => $plannedSession->makeHidden(['profile'])->toArray())
                        ->values(),
                ])
                ->values(),
        ];

        $filename = 'your-kayaking-journal-export-'.now()->format('Y-m-d').'.json';

        return response()->streamDownload(
            fn () => print (json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
            $filename,
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
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
        $media = app(SessionMediaService::class);

        $user->ownedProfiles()
            ->with(['sessions:id,profile_id,gpx_path,fit_path,session_photo_path'])
            ->get()
            ->flatMap(fn ($profile) => $profile->sessions)
            ->each(function (PaddleSession $session) use ($media): void {
                collect([
                    $session->gpx_path,
                    $session->fit_path,
                    $session->session_photo_path,
                ])
                    ->filter()
                    ->unique()
                    ->each(fn (string $path) => $media->delete($path));
            });

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
