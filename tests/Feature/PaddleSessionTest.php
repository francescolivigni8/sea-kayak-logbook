<?php

namespace Tests\Feature;

use App\Models\PaddleSession;
use App\Models\SessionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaddleSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_view_the_sessions_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('sessions.index'))
            ->assertOk();
    }

    public function test_library_splits_planned_sessions_and_logged_sessions(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();

        $profile->plannedSessions()->create([
            'created_by_user_id' => $user->id,
            'title' => 'Future route',
            'plan_date' => '2026-04-18',
            'timezone' => $profile->timezone,
            'speed_knots' => 3.5,
            'distance_km' => 6.4,
            'estimated_duration_minutes' => 60,
        ]);

        $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-06',
            'title' => 'Logged route',
            'launch_name' => 'Reykjavik',
            'route_category' => 'journey',
            'distance_km' => 8.4,
        ]);

        $this->actingAs($user)
            ->get(route('sessions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sessions/Index')
                ->where('stats.plannedCount', 1)
                ->where('stats.sessionCount', 1)
                ->has('plannedSessions', 1)
                ->has('sessions', 1));
    }

    public function test_create_session_uses_profile_default_map_view(): void
    {
        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();
        $settings = $profile->settings ?? [];
        $settings['default_map_view'] = [
            'lat' => 65.6885,
            'lng' => -18.1262,
            'zoom' => 12,
        ];
        $profile->settings = $settings;
        $profile->save();

        $this->actingAs($user)
            ->get(route('sessions.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('profile.defaultMapView.lat', 65.6885)
                ->where('profile.defaultMapView.lng', -18.1262)
                ->where('profile.defaultMapView.zoom', 12));
    }

    public function test_quick_session_create_prefills_recent_title_area_and_place(): void
    {
        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();

        $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-10',
            'title' => 'Morning benchmark',
            'area_name' => 'Faxafloi',
            'launch_name' => 'Nautholsvik',
            'route_category' => 'benchmark',
            'distance_km' => 7.2,
        ]);

        $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-11',
            'title' => 'Evening harbor loop',
            'area_name' => '',
            'launch_name' => 'Grotta',
            'route_category' => 'journey',
            'distance_km' => 8.1,
        ]);

        $this->actingAs($user)
            ->get(route('sessions.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('formDefaults.title', 'Evening harbor loop')
                ->where('formDefaults.area_name', 'Faxafloi')
                ->where('formDefaults.launch_name', 'Grotta')
                ->where('quickEntryMemory.prefill.title', 'Evening harbor loop')
                ->where('quickEntryMemory.prefill.areaName', 'Faxafloi')
                ->where('quickEntryMemory.prefill.placeName', 'Grotta')
                ->where('quickEntryMemory.suggestions.titles.0', 'Evening harbor loop')
                ->where('quickEntryMemory.suggestions.titles.1', 'Morning benchmark')
                ->where('quickEntryMemory.suggestions.areas.0', 'Faxafloi')
                ->where('quickEntryMemory.suggestions.places.0', 'Grotta')
                ->where('quickEntryMemory.suggestions.places.1', 'Nautholsvik'));
    }

    public function test_authenticated_users_can_view_a_session_detail_page(): void
    {
        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();
        $session = $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-06',
            'title' => 'Faxafloi detail page',
            'launch_name' => 'Reykjavik',
            'route_category' => 'journey',
            'distance_km' => 8.4,
        ]);

        $this->actingAs($user)
            ->get(route('sessions.show', $session))
            ->assertOk()
            ->assertSee('Faxafloi detail page');
    }

    public function test_authenticated_users_can_open_a_session_share_page(): void
    {
        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();
        $profile->settings = [
            ...($profile->settings ?? []),
            'unit_preferences' => [
                'distance' => 'nm',
                'speed' => 'kt',
                'wind' => 'kt',
                'current' => 'kmh',
                'temperature' => 'f',
            ],
        ];
        $profile->save();
        $session = $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-06',
            'title' => 'Shared Reykjanes paddle',
            'launch_name' => 'Hafnarfjordur',
            'route_category' => 'journey',
            'distance_km' => 11.112,
            'duration_minutes' => 60,
            'wind_avg_ms' => 10.0,
            'current_knots' => 2.0,
            'air_temp_c' => 10.0,
            'notes_public' => 'Useful dry-run before the bigger crossing.',
        ]);

        $this->actingAs($user)
            ->get(route('sessions.share', $session))
            ->assertOk()
            ->assertViewIs('sessions.share')
            ->assertSee('Shared Reykjanes paddle')
            ->assertSee('Print / Save PDF')
            ->assertSee('Useful dry-run before the bigger crossing.')
            ->assertSee('6.0 nm')
            ->assertSee('6.0 kt')
            ->assertSee('19.4 kt')
            ->assertSee('3.7 km/h')
            ->assertSee('50.0 F');
    }

    public function test_users_cannot_open_share_pages_for_other_profiles(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $session = $owner->resolveActiveProfile()->sessions()->create([
            'recorded_by_user_id' => $owner->id,
            'session_date' => '2026-04-06',
            'title' => 'Private owner session',
            'launch_name' => 'Reykjavik',
            'route_category' => 'journey',
            'distance_km' => 7.5,
        ]);

        $this->actingAs($otherUser)
            ->get(route('sessions.share', $session))
            ->assertNotFound();
    }

    public function test_authenticated_users_can_store_a_paddle_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('sessions.store'), [
                'title' => 'Evening harbor loop',
                'session_date' => '2026-04-06',
                'launch_name' => 'Reykjavik',
                'route_category' => 'journey',
                'kayak_used' => 'Valley Etain 17-7',
                'paddle_used' => 'Werner Cyprus',
                'distance_km' => '8.4',
                'duration_minutes' => '94',
                'is_public' => true,
            ])
            ->assertRedirect(route('dashboard'));

        $profile = $user->resolveActiveProfile();

        $this->assertDatabaseHas(PaddleSession::class, [
            'profile_id' => $profile->id,
            'title' => 'Evening harbor loop',
            'launch_name' => 'Reykjavik',
            'route_category' => 'journey',
            'kayak_used' => 'Valley Etain 17-7',
            'paddle_used' => 'Werner Cyprus',
        ]);
    }

    public function test_manual_distance_accepts_decimal_commas(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('sessions.store'), [
                'title' => 'Decimal comma paddle',
                'session_date' => '2026-04-06',
                'launch_name' => 'Reykjavik',
                'route_category' => 'journey',
                'distance_km' => '9,3',
            ])
            ->assertRedirect(route('dashboard'));

        $profile = $user->resolveActiveProfile();

        $this->assertDatabaseHas(PaddleSession::class, [
            'profile_id' => $profile->id,
            'title' => 'Decimal comma paddle',
            'distance_km' => 9.3,
        ]);
    }

    public function test_sessions_can_be_grouped_into_collections(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('sessions.store'), [
                'title' => 'Anglesey day one',
                'session_date' => '2026-04-06',
                'launch_name' => 'Trearddur Bay',
                'route_category' => 'journey',
                'distance_km' => '12.4',
                'category_names_text' => 'Anglesey 2026, Club paddles',
            ])
            ->assertRedirect(route('dashboard'));

        $profile = $user->resolveActiveProfile();
        $session = PaddleSession::query()
            ->where('profile_id', $profile->id)
            ->where('title', 'Anglesey day one')
            ->firstOrFail();

        $this->assertDatabaseHas(SessionCategory::class, [
            'profile_id' => $profile->id,
            'name' => 'Anglesey 2026',
            'slug' => 'anglesey-2026',
        ]);
        $this->assertSame(
            ['Anglesey 2026', 'Club paddles'],
            $session->categories()->orderBy('name')->pluck('name')->all(),
        );

        $this->actingAs($user)
            ->get(route('sessions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sessions/Index')
                ->where('stats.collectionCount', 2)
                ->has('categoryGroups', 2)
                ->where('categoryGroups.0.name', 'Anglesey 2026')
                ->where('categoryGroups.0.sessionCount', 1)
                ->where('sessions.0.categories.0.name', 'Anglesey 2026'));
    }

    public function test_library_can_create_collection_folders(): void
    {
        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();

        $this->actingAs($user)
            ->post(route('session-categories.store'), [
                'name' => 'Club paddles',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas(SessionCategory::class, [
            'profile_id' => $profile->id,
            'name' => 'Club paddles',
            'slug' => 'club-paddles',
        ]);

        $this->actingAs($user)
            ->get(route('sessions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sessions/Index')
                ->where('stats.collectionCount', 1)
                ->where('categoryGroups.0.name', 'Club paddles'));
    }

    public function test_logged_sessions_can_be_attached_to_collection_folders(): void
    {
        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();
        $category = $profile->sessionCategories()->create([
            'name' => 'Anglesey 2026',
            'slug' => 'anglesey-2026',
        ]);
        $session = $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-06',
            'title' => 'Anglesey day two',
            'launch_name' => 'Holyhead',
            'route_category' => 'journey',
            'distance_km' => 9.2,
        ]);

        $this->actingAs($user)
            ->post(route('session-categories.sessions.attach', [$category, $session]))
            ->assertRedirect();

        $this->assertDatabaseHas('paddle_session_category', [
            'session_category_id' => $category->id,
            'paddle_session_id' => $session->id,
        ]);

        $this->actingAs($user)
            ->get(route('sessions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sessions/Index')
                ->where('categoryGroups.0.name', 'Anglesey 2026')
                ->where('categoryGroups.0.sessionCount', 1)
                ->where('sessions.0.categories.0.name', 'Anglesey 2026'));
    }

    public function test_logged_sessions_can_be_batch_attached_to_collection_folders(): void
    {
        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();
        $category = $profile->sessionCategories()->create([
            'name' => 'Club paddles',
            'slug' => 'club-paddles',
        ]);
        $sessions = collect(['Club night one', 'Club night two'])
            ->map(fn (string $title) => $profile->sessions()->create([
                'recorded_by_user_id' => $user->id,
                'session_date' => '2026-04-06',
                'title' => $title,
                'launch_name' => 'Reykjavik',
                'route_category' => 'journey',
                'distance_km' => 6.4,
            ]));

        $this->actingAs($user)
            ->post(route('session-categories.sessions.attach-many', $category), [
                'session_ids' => $sessions->pluck('id')->all(),
            ])
            ->assertRedirect();

        $sessions->each(function (PaddleSession $session) use ($category): void {
            $this->assertDatabaseHas('paddle_session_category', [
                'session_category_id' => $category->id,
                'paddle_session_id' => $session->id,
            ]);
        });

        $this->actingAs($user)
            ->get(route('sessions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sessions/Index')
                ->where('categoryGroups.0.name', 'Club paddles')
                ->where('categoryGroups.0.sessionCount', 2));
    }

    public function test_manual_sessions_can_store_launch_and_landing_coordinates_without_a_track_file(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('sessions.store'), [
                'title' => 'Manual geolocated paddle',
                'session_date' => '2026-04-06',
                'launch_name' => 'Reykjavik',
                'launch_lat' => '64.146600',
                'launch_lng' => '-21.942600',
                'landing_name' => 'Grotta',
                'landing_lat' => '64.167200',
                'landing_lng' => '-22.022600',
                'route_category' => 'journey',
                'distance_km' => '6.2',
                'is_public' => true,
            ])
            ->assertRedirect();

        $profile = $user->resolveActiveProfile();

        $this->assertDatabaseHas(PaddleSession::class, [
            'profile_id' => $profile->id,
            'title' => 'Manual geolocated paddle',
            'launch_lat' => 64.1466,
            'launch_lng' => -21.9426,
            'landing_lat' => 64.1672,
            'landing_lng' => -22.0226,
        ]);
    }

    public function test_manual_sessions_can_store_an_editable_route_trace_without_a_track_file(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('sessions.store'), [
                'title' => 'Manual traced paddle',
                'session_date' => '2026-04-06',
                'launch_name' => 'Reykjavik',
                'landing_name' => 'Grotta',
                'route_category' => 'journey',
                'manual_route_waypoints' => json_encode([
                    ['lat' => 64.1466, 'lng' => -21.9426],
                    ['lat' => 64.1531, 'lng' => -21.9782],
                    ['lat' => 64.1595, 'lng' => -22.0043],
                    ['lat' => 64.1672, 'lng' => -22.0226],
                ]),
            ])
            ->assertRedirect();

        $profile = $user->resolveActiveProfile();
        $session = PaddleSession::query()
            ->where('profile_id', $profile->id)
            ->where('title', 'Manual traced paddle')
            ->firstOrFail();

        $this->assertIsArray($session->route_profile);
        $this->assertCount(4, $session->route_profile);
        $this->assertNotNull($session->route_points);
        $this->assertSame(64.1531, (float) $session->route_profile[1]['lat']);
        $this->assertSame(-22.0043, (float) $session->route_profile[2]['lng']);
        $this->assertSame(64.1466, (float) $session->launch_lat);
        $this->assertSame(-22.0226, (float) $session->landing_lng);
        $this->assertGreaterThan(0, (float) $session->distance_km);
    }

    public function test_manual_gpx_uploads_are_parsed_into_route_data(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $gpx = UploadedFile::fake()->createWithContent('harbor-loop.gpx', <<<'GPX'
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="test">
  <trk>
    <name>Harbor Loop</name>
    <trkseg>
      <trkpt lat="64.1500" lon="-21.9500"><time>2026-04-06T18:00:00Z</time></trkpt>
      <trkpt lat="64.1510" lon="-21.9400"><time>2026-04-06T18:10:00Z</time></trkpt>
      <trkpt lat="64.1520" lon="-21.9300"><time>2026-04-06T18:20:00Z</time></trkpt>
    </trkseg>
  </trk>
</gpx>
GPX);

        $this->actingAs($user)
            ->post(route('sessions.store'), [
                'title' => 'GPX-backed paddle',
                'session_date' => '2026-04-06',
                'launch_name' => 'Reykjavik',
                'route_category' => 'journey',
                'gpx_file' => $gpx,
                'is_public' => true,
            ])
            ->assertRedirect();

        $profile = $user->resolveActiveProfile();
        $session = PaddleSession::query()
            ->where('profile_id', $profile->id)
            ->where('title', 'GPX-backed paddle')
            ->firstOrFail();

        $this->assertNotNull($session->gpx_path);
        $this->assertNotEmpty($session->route_points);
        $this->assertNotEmpty($session->route_profile);
        $this->assertGreaterThan(0, (float) $session->distance_km);
        $this->assertGreaterThan(0, (int) $session->duration_minutes);
    }

    public function test_manual_fit_uploads_are_parsed_into_track_data(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $fitFixture = file_get_contents(base_path('vendor/adriangibbons/php-fit-file-analysis/demo/fit_files/road-cycling.fit'));
        $this->assertNotFalse($fitFixture);

        $fit = UploadedFile::fake()->createWithContent('road-cycling.fit', $fitFixture);

        $this->actingAs($user)
            ->post(route('sessions.store'), [
                'title' => 'FIT-backed paddle',
                'session_date' => '2026-04-06',
                'launch_name' => 'Reykjavik',
                'route_category' => 'journey',
                'fit_file' => $fit,
                'is_public' => true,
            ])
            ->assertRedirect();

        $profile = $user->resolveActiveProfile();
        $session = PaddleSession::query()
            ->where('profile_id', $profile->id)
            ->where('title', 'FIT-backed paddle')
            ->firstOrFail();

        $this->assertNotNull($session->fit_path);
        $this->assertNotEmpty($session->route_profile);
        $this->assertGreaterThan(0, (float) $session->distance_km);
        $this->assertGreaterThan(0, (int) $session->duration_minutes);
        $this->assertNotNull($session->launch_lat);
        $this->assertNotNull($session->launch_lng);
    }

    public function test_imported_sessions_can_be_updated_with_observations_without_reuploading_a_track_file(): void
    {
        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();
        $session = $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-06',
            'title' => 'Imported Garmin session',
            'launch_name' => null,
            'route_category' => 'journey',
            'distance_km' => 0,
            'gpx_path' => 'gpx/imports/garmin-track.gpx',
            'garmin_gpx_name' => 'garmin-track.gpx',
        ]);

        $this->actingAs($user)
            ->patch(route('sessions.update', $session), [
                'title' => 'Imported Garmin session',
                'session_date' => '2026-04-06',
                'launch_name' => '',
                'route_category' => 'journey',
                'notes_public' => 'Too much faff at the launch. Pack cleaner next time.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas(PaddleSession::class, [
            'id' => $session->id,
            'launch_name' => null,
            'notes_public' => 'Too much faff at the launch. Pack cleaner next time.',
        ]);
    }

    public function test_manual_sessions_without_route_files_still_need_distance(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('sessions.store'), [
                'title' => 'Distance missing',
                'session_date' => '2026-04-06',
                'route_category' => 'journey',
            ])
            ->assertSessionHasErrors('distance_km');
    }

    public function test_manual_sessions_can_autofill_weather_from_stormglass(): void
    {
        config()->set('kayak.weather.providers.stormglass.api_key', 'test-key');

        Http::fake([
            'api.stormglass.io/v2/weather/point*' => Http::response([
                'hours' => [[
                    'time' => '2026-04-06T18:00:00+00:00',
                    'windSpeed' => ['sg' => 7.2],
                    'gust' => ['sg' => 10.4],
                    'windDirection' => ['sg' => 215],
                    'precipitation' => ['sg' => 0.8],
                    'airTemperature' => ['sg' => 8.5],
                    'waterTemperature' => ['sg' => 6.1],
                    'visibility' => ['sg' => 12000],
                    'currentSpeed' => ['sg' => 0.4],
                    'currentDirection' => ['sg' => 155],
                    'waveHeight' => ['sg' => 0.7],
                    'swellHeight' => ['sg' => 0.9],
                    'swellPeriod' => ['sg' => 5.5],
                    'swellDirection' => ['sg' => 235],
                ]],
            ]),
            'api.stormglass.io/v2/tide/extremes/point*' => Http::response([
                'data' => [
                    ['time' => '2026-04-06T15:45:00+00:00', 'type' => 'low', 'height' => 0.4],
                    ['time' => '2026-04-06T21:55:00+00:00', 'type' => 'high', 'height' => 2.9],
                ],
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('sessions.store'), [
                'title' => 'Stormglass-backed paddle',
                'session_date' => '2026-04-06',
                'start_time_local' => '18:00',
                'launch_name' => 'Reykjavik',
                'launch_lat' => '64.146600',
                'launch_lng' => '-21.942600',
                'route_category' => 'journey',
                'distance_km' => '8.4',
                'autofill_weather' => true,
            ])
            ->assertRedirect();

        $profile = $user->resolveActiveProfile();
        $session = PaddleSession::query()
            ->where('profile_id', $profile->id)
            ->where('title', 'Stormglass-backed paddle')
            ->firstOrFail();

        $this->assertSame(4, $session->wind_beaufort);
        $this->assertSame(7.2, (float) $session->wind_avg_ms);
        $this->assertSame(0.8, (float) $session->current_knots);
        $this->assertSame('clear', $session->visibility_code);
        $this->assertSame('flooding', $session->tide_state);
        $this->assertSame('moderate', $session->rain_severity);
        $this->assertSame('moderate', $session->wind_severity);
        $this->assertSame('high', $session->temperature_severity);
        $this->assertSame('high', $session->forecast_severity);
        $this->assertNotNull($session->weather_summary);
    }

    public function test_weather_preview_endpoint_returns_stormglass_values_before_save(): void
    {
        config()->set('kayak.weather.providers.stormglass.api_key', 'test-key');

        Http::fake([
            'api.stormglass.io/v2/weather/point*' => Http::response([
                'hours' => [[
                    'time' => '2026-04-06T18:00:00+00:00',
                    'windSpeed' => ['sg' => 7.2],
                    'gust' => ['sg' => 10.4],
                    'windDirection' => ['sg' => 215],
                    'precipitation' => ['sg' => 0.8],
                    'airTemperature' => ['sg' => 8.5],
                    'waterTemperature' => ['sg' => 6.1],
                    'visibility' => ['sg' => 12000],
                    'currentSpeed' => ['sg' => 0.4],
                    'currentDirection' => ['sg' => 155],
                    'waveHeight' => ['sg' => 0.7],
                    'swellHeight' => ['sg' => 0.9],
                    'swellPeriod' => ['sg' => 5.5],
                    'swellDirection' => ['sg' => 235],
                ]],
            ]),
            'api.stormglass.io/v2/tide/extremes/point*' => Http::response([
                'data' => [
                    ['time' => '2026-04-06T15:45:00+00:00', 'type' => 'low', 'height' => 0.4],
                    ['time' => '2026-04-06T21:55:00+00:00', 'type' => 'high', 'height' => 2.9],
                ],
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('sessions.weather-preview', [
                'session_date' => '2026-04-06',
                'start_time_local' => '18:00',
                'launch_lat' => '64.146600',
                'launch_lng' => '-21.942600',
            ]))
            ->assertOk()
            ->assertJsonPath('status', 'filled')
            ->assertJsonPath('fields.wind_beaufort', 4)
            ->assertJsonPath('fields.tide_state', 'flooding')
            ->assertJsonPath('fields.current_knots', 0.8)
            ->assertJsonPath('fields.wind_severity', 'moderate')
            ->assertJsonPath('fields.temperature_severity', 'high')
            ->assertJsonPath('fields.forecast_severity', 'high')
            ->assertJsonPath('fields.rain_severity', 'moderate');
    }
}
