<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\PaddleSession;
use App\Models\PlannedSession;
use App\Models\Profile;
use App\Models\User;
use App\Support\SessionMediaService;
use App\Support\UnitPreferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

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
            'feedbackStatus' => $request->session()->get('feedback_status'),
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
                'backupUrl' => route('profile.backup'),
                'exportUrl' => route('profile.export'),
                'feedbackUrl' => route('feedback.store'),
                'feedbackContext' => (string) Str::limit((string) $request->query('from', ''), 180, ''),
                'settings' => [
                    'paddlerName' => (string) data_get($settings, 'paddler_name', ''),
                    'kayakClub' => (string) data_get($settings, 'kayak_club', ''),
                    'kayaksOwnedText' => implode(', ', data_get($settings, 'kayaks_owned', [])),
                    'paddlesOwnedText' => implode(', ', data_get($settings, 'paddles_owned', [])),
                    'unitPreferences' => UnitPreferences::fromSettings($settings),
                    'defaultMapLat' => (string) data_get($settings, 'default_map_view.lat', '64.1670'),
                    'defaultMapLng' => (string) data_get($settings, 'default_map_view.lng', '-21.8210'),
                    'defaultMapZoom' => (string) data_get($settings, 'default_map_view.zoom', '10'),
                    'hasCustomDefaultMapView' => is_array(data_get($settings, 'default_map_view')),
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
        $settings['unit_preferences'] = UnitPreferences::sanitize([
            'distance' => $validated['distance_unit'] ?? null,
            'speed' => $validated['speed_unit'] ?? null,
            'wind' => $validated['wind_unit'] ?? null,
            'current' => $validated['current_unit'] ?? null,
            'temperature' => $validated['temperature_unit'] ?? null,
        ], UnitPreferences::fromSettings($settings));
        $settings['planning_unit_system'] = UnitPreferences::legacyPreset($settings['unit_preferences']);
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
        $payload = $this->buildExportPayload($request->user());

        $filename = 'your-kayaking-journal-export-'.now()->format('Y-m-d').'.json';

        return response()->streamDownload(
            fn () => print (json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
            $filename,
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    public function backup(Request $request): StreamedResponse
    {
        $user = $request->user();
        $payload = $this->buildExportPayload($user);
        $ownedProfiles = $this->ownedProfilesForExport($user);
        $media = app(SessionMediaService::class);
        $zipPath = storage_path('app/tmp/'.Str::uuid().'.zip');
        $zipDirectory = dirname($zipPath);

        if (! is_dir($zipDirectory)) {
            mkdir($zipDirectory, 0775, true);
        }

        $zip = new ZipArchive;
        $status = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        abort_unless($status === true, 500, 'Backup archive could not be created.');

        $zip->addFromString(
            'journal-export.json',
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}',
        );

        $disk = $media->disk();

        foreach ($ownedProfiles as $profile) {
            $profileDirectory = 'profiles/'.$this->safeZipPath($profile->slug ?: $profile->name ?: 'profile-'.$profile->id);

            foreach ($profile->plannedSessions as $plannedSession) {
                $gpx = $this->plannedSessionGpx($plannedSession);

                if ($gpx !== null) {
                    $zip->addFromString(
                        $profileDirectory.'/planned-routes/'.$this->plannedRouteFilename($plannedSession),
                        $gpx,
                    );
                }
            }

            foreach ($profile->sessions as $session) {
                foreach ([
                    'gpx_path' => 'gpx',
                    'fit_path' => 'fit',
                    'session_photo_path' => 'photos',
                ] as $attribute => $bucket) {
                    $path = $session->{$attribute};

                    if (! $path || ! $disk->exists($path)) {
                        continue;
                    }

                    $stream = $disk->readStream($path);

                    if (! is_resource($stream)) {
                        continue;
                    }

                    $contents = stream_get_contents($stream);
                    fclose($stream);

                    if (! is_string($contents) || $contents === '') {
                        continue;
                    }

                    $zip->addFromString(
                        $profileDirectory.'/media/'.$bucket.'/'.$this->safeZipPath(basename($path)),
                        $contents,
                    );
                }
            }
        }

        $zip->close();

        $filename = 'your-kayaking-journal-backup-'.now()->format('Y-m-d').'.zip';

        return response()->streamDownload(
            function () use ($zipPath): void {
                $stream = fopen($zipPath, 'rb');

                if (is_resource($stream)) {
                    fpassthru($stream);
                    fclose($stream);
                }

                @unlink($zipPath);
            },
            $filename,
            ['Content-Type' => 'application/zip'],
        );
    }

    private function buildExportPayload(User $user): array
    {
        $ownedProfiles = $this->ownedProfilesForExport($user);

        return [
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
            'feedback_reports' => $user->feedbackReports()
                ->orderByDesc('created_at')
                ->get()
                ->map(fn ($feedback) => [
                    'kind' => $feedback->kind,
                    'subject' => $feedback->subject,
                    'page_context' => $feedback->page_context,
                    'message' => $feedback->message,
                    'submitted_from_path' => $feedback->submitted_from_path,
                    'status' => $feedback->status,
                    'created_at' => $feedback->created_at?->toIso8601String(),
                ])
                ->values(),
            'profiles' => $ownedProfiles
                ->map(fn (Profile $profile) => [
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
    }

    private function ownedProfilesForExport(User $user)
    {
        return $user->ownedProfiles()
            ->with([
                'memberships',
                'sessions.categories',
                'plannedSessions',
                'sessionCategories.sessions:id,title,session_date,distance_km',
            ])
            ->get();
    }

    private function plannedSessionGpx(PlannedSession $plannedSession): ?string
    {
        $points = collect($plannedSession->route_profile ?? [])
            ->filter(fn (mixed $point) => is_array($point) && isset($point['lat'], $point['lng']))
            ->values();

        if ($points->count() < 2) {
            return null;
        }

        $name = e($plannedSession->title ?: 'Planned route');
        $time = ($plannedSession->start_at ?? Carbon::parse($plannedSession->plan_date ?? now(), $plannedSession->timezone ?: 'UTC'))
            ->copy()
            ->utc()
            ->toIso8601String();

        $routePoints = $points
            ->map(function (array $point): string {
                $lat = number_format((float) $point['lat'], 6, '.', '');
                $lng = number_format((float) $point['lng'], 6, '.', '');

                return sprintf('    <rtept lat="%s" lon="%s" />', $lat, $lng);
            })
            ->implode("\n");

        return <<<GPX
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="Your Kayaking Journal" xmlns="http://www.topografix.com/GPX/1/1">
  <metadata>
    <name>{$name}</name>
    <time>{$time}</time>
  </metadata>
  <rte>
    <name>{$name}</name>
{$routePoints}
  </rte>
</gpx>
GPX;
    }

    private function plannedRouteFilename(PlannedSession $plannedSession): string
    {
        $slug = Str::slug($plannedSession->title ?: 'planned-route');

        return trim($slug !== '' ? $slug : 'planned-route', '-').'-'.$plannedSession->id.'.gpx';
    }

    private function safeZipPath(string $value): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9._-]+/', '-', trim($value)) ?: 'item';

        return trim($sanitized, '-.') ?: 'item';
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
