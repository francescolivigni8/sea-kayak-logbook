<?php

namespace Tests\Feature;

use App\Models\PlannedSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlanningTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_open_the_planning_page(): void
    {
        $this->withoutVite();
        config()->set('kayak.weather.providers.stormglass.api_key', null);
        config()->set('kayak.weather.providers.open_meteo.enabled', true);

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
            ->get(route('planning.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('planning/Index')
                ->where('profile.defaultMapView.lat', 65.6885)
                ->where('profile.defaultMapView.lng', -18.1262)
                ->where('profile.defaultMapView.zoom', 12)
                ->where('weatherAutofillAvailable', true));
    }

    public function test_planning_weather_preview_returns_waypoint_conditions(): void
    {
        Cache::flush();
        config()->set('kayak.weather.providers.stormglass.api_key', 'test-key');

        Http::fake([
            'api.stormglass.io/v2/weather/point*' => Http::response([
                'hours' => [[
                    'time' => '2026-04-18T09:00:00+00:00',
                    'windSpeed' => ['sg' => 5.8],
                    'gust' => ['sg' => 8.2],
                    'windDirection' => ['sg' => 205],
                    'precipitation' => ['sg' => 0.1],
                    'cloudCover' => ['sg' => 46],
                    'airTemperature' => ['sg' => 9.5],
                    'waterTemperature' => ['sg' => 6.4],
                    'visibility' => ['sg' => 9000],
                    'currentSpeed' => ['sg' => 0.35],
                    'currentDirection' => ['sg' => 145],
                    'waveHeight' => ['sg' => 0.5],
                    'swellHeight' => ['sg' => 0.7],
                    'swellPeriod' => ['sg' => 4.8],
                    'swellDirection' => ['sg' => 230],
                ]],
            ]),
            'api.stormglass.io/v2/tide/extremes/point*' => Http::response([
                'data' => [
                    ['time' => '2026-04-18T06:30:00+00:00', 'type' => 'low', 'height' => 0.4],
                    ['time' => '2026-04-18T12:20:00+00:00', 'type' => 'high', 'height' => 2.8],
                ],
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('planning.weather-preview', [
                'plan_date' => '2026-04-18',
                'start_time_local' => '09:00',
                'lat' => '64.146600',
                'lng' => '-21.942600',
                'label' => 'Launch',
            ]))
            ->assertOk()
            ->assertJsonPath('status', 'filled')
            ->assertJsonPath('point.label', 'Launch')
            ->assertJsonPath('fields.wind_beaufort', 4)
            ->assertJsonPath('fields.tide_state', 'flooding')
            ->assertJsonPath('fields.current_knots', 0.7)
            ->assertJsonPath('fields.wind_direction_deg', 205)
            ->assertJsonPath('timeline.0.fields.wind_beaufort', 4)
            ->assertJsonPath('timeline.0.fields.precipitation_mm', 0.1)
            ->assertJsonPath('timeline.0.fields.cloud_cover_percent', 46);
    }

    public function test_planning_weather_preview_reports_stormglass_quota_errors(): void
    {
        Cache::flush();
        config()->set('kayak.weather.providers.stormglass.api_key', 'test-key');
        config()->set('kayak.weather.providers.open_meteo.enabled', false);

        Http::fake([
            'api.stormglass.io/v2/weather/point*' => Http::response([
                'message' => 'Too Many Requests',
            ], 429),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('planning.weather-preview', [
                'plan_date' => '2026-04-18',
                'start_time_local' => '09:00',
                'lat' => '64.146600',
                'lng' => '-21.942600',
                'label' => 'Launch',
            ]))
            ->assertOk()
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('httpStatus', 429)
            ->assertJsonPath('message', 'Stormglass daily request quota is exhausted. Try again after the reset, reduce waypoints, or upgrade the request limit.');
    }

    public function test_planning_weather_preview_reports_stormglass_auth_details(): void
    {
        Cache::flush();
        config()->set('kayak.weather.providers.stormglass.api_key', 'test-key');
        config()->set('kayak.weather.providers.open_meteo.enabled', false);

        Http::fake([
            'api.stormglass.io/v2/weather/point*' => Http::response([
                'message' => 'Invalid API key',
            ], 401),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('planning.weather-preview', [
                'plan_date' => '2026-04-18',
                'start_time_local' => '09:00',
                'lat' => '64.146600',
                'lng' => '-21.942600',
                'label' => 'Route area',
            ]))
            ->assertOk()
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('httpStatus', 401)
            ->assertJsonPath('providerMessage', 'Invalid API key')
            ->assertJsonPath('message', 'Stormglass returned HTTP 401. The API key is missing or invalid in Stormglass. Check STORMGLASS_API_KEY in Laravel Cloud, redeploy, or try STORMGLASS_AUTH_VALUE_PREFIX=Bearer. Stormglass said: Invalid API key');
    }

    public function test_planning_weather_preview_caps_large_route_offsets(): void
    {
        config()->set('kayak.weather.providers.stormglass.api_key', null);
        config()->set('kayak.weather.providers.open_meteo.enabled', false);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('planning.weather-preview', [
                'plan_date' => '2026-04-18',
                'start_time_local' => '09:00',
                'lat' => '64.146600',
                'lng' => '-21.942600',
                'label' => 'Route area',
                'offset_minutes' => '2000',
            ]))
            ->assertOk()
            ->assertJsonPath('status', 'skipped')
            ->assertJsonPath('point.offsetMinutes', 1440);
    }

    public function test_planning_weather_preview_falls_back_to_open_meteo_when_stormglass_fails(): void
    {
        Cache::flush();
        config()->set('kayak.weather.providers.stormglass.api_key', 'test-key');
        config()->set('kayak.weather.providers.open_meteo.enabled', true);

        Http::fake([
            'api.stormglass.io/v2/weather/point*' => Http::response([
                'message' => 'Invalid API key',
            ], 403),
            'api.open-meteo.com/v1/forecast*' => Http::response([
                'hourly' => [
                    'time' => [
                        '2026-04-18T06:00',
                        '2026-04-18T09:00',
                        '2026-04-18T12:00',
                    ],
                    'wind_speed_10m' => [4.2, 6.1, 5.5],
                    'wind_gusts_10m' => [6.4, 8.4, 7.2],
                    'wind_direction_10m' => [190, 205, 210],
                    'temperature_2m' => [8.8, 9.5, 10.1],
                    'precipitation' => [0, 0.2, 0.1],
                    'cloud_cover' => [35, 46, 52],
                    'visibility' => [11000, 9000, 8000],
                ],
            ]),
            'marine-api.open-meteo.com/v1/marine*' => Http::response([
                'hourly_units' => [
                    'ocean_current_velocity' => 'km/h',
                ],
                'hourly' => [
                    'time' => [
                        '2026-04-18T06:00',
                        '2026-04-18T09:00',
                        '2026-04-18T12:00',
                    ],
                    'wave_height' => [0.4, 0.5, 0.6],
                    'swell_wave_height' => [0.5, 0.7, 0.8],
                    'swell_wave_period' => [4.2, 4.8, 5.1],
                    'swell_wave_direction' => [220, 230, 235],
                    'sea_surface_temperature' => [6.2, 6.4, 6.5],
                    'ocean_current_velocity' => [1.0, 1.3, 1.2],
                    'ocean_current_direction' => [140, 145, 150],
                    'sea_level_height_msl' => [1.0, 1.2, 1.4],
                ],
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('planning.weather-preview', [
                'plan_date' => '2026-04-18',
                'start_time_local' => '09:00',
                'lat' => '64.146600',
                'lng' => '-21.942600',
                'label' => 'Route area',
            ]))
            ->assertOk()
            ->assertJsonPath('status', 'filled')
            ->assertJsonPath('provider', 'open_meteo')
            ->assertJsonPath('fallbackFrom.provider', 'stormglass')
            ->assertJsonPath('fields.wind_beaufort', 4)
            ->assertJsonPath('fields.tide_state', 'flooding')
            ->assertJsonPath('fields.current_knots', 0.7)
            ->assertJsonPath('fields.sea_temp_c', 6.4)
            ->assertJsonPath('timeline.3.fields.wind_beaufort', 4);
    }

    public function test_stormglass_source_can_be_omitted_for_restricted_accounts(): void
    {
        Cache::flush();
        config()->set('kayak.weather.providers.stormglass.api_key', 'test-key');
        config()->set('kayak.weather.providers.stormglass.source', 'none');

        Http::fake([
            'api.stormglass.io/v2/weather/point*' => Http::response([
                'hours' => [[
                    'time' => '2026-04-18T09:00:00+00:00',
                    'windSpeed' => ['noaa' => 5.8],
                    'gust' => ['noaa' => 8.2],
                    'windDirection' => ['noaa' => 205],
                ]],
            ]),
            'api.stormglass.io/v2/tide/extremes/point*' => Http::response([
                'data' => [],
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('planning.weather-preview', [
                'plan_date' => '2026-04-18',
                'start_time_local' => '09:00',
                'lat' => '64.146600',
                'lng' => '-21.942600',
                'label' => 'Route area',
            ]))
            ->assertOk()
            ->assertJsonPath('status', 'filled');

        Http::assertSent(fn ($request) => ! array_key_exists('source', $request->data()));
    }

    public function test_planning_weather_preview_reuses_cached_stormglass_points(): void
    {
        Cache::flush();
        config()->set('kayak.weather.providers.stormglass.api_key', 'test-key');

        Http::fake([
            'api.stormglass.io/v2/weather/point*' => Http::response([
                'hours' => [[
                    'time' => '2026-04-18T09:00:00+00:00',
                    'windSpeed' => ['sg' => 5.8],
                    'gust' => ['sg' => 8.2],
                    'windDirection' => ['sg' => 205],
                    'cloudCover' => ['sg' => 46],
                    'airTemperature' => ['sg' => 9.5],
                    'waterTemperature' => ['sg' => 6.4],
                    'currentSpeed' => ['sg' => 0.35],
                    'waveHeight' => ['sg' => 0.5],
                ]],
            ]),
            'api.stormglass.io/v2/tide/extremes/point*' => Http::response([
                'data' => [
                    ['time' => '2026-04-18T06:30:00+00:00', 'type' => 'low', 'height' => 0.4],
                    ['time' => '2026-04-18T12:20:00+00:00', 'type' => 'high', 'height' => 2.8],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $params = [
            'plan_date' => '2026-04-18',
            'start_time_local' => '09:00',
            'lat' => '64.146600',
            'lng' => '-21.942600',
            'label' => 'Launch',
        ];

        $this->actingAs($user)
            ->getJson(route('planning.weather-preview', $params))
            ->assertOk()
            ->assertJsonPath('status', 'filled');

        $this->actingAs($user)
            ->getJson(route('planning.weather-preview', $params))
            ->assertOk()
            ->assertJsonPath('status', 'filled');

        Http::assertSentCount(2);
    }

    public function test_planning_page_can_save_a_planned_session(): void
    {
        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();

        $response = $this->actingAs($user)
            ->post(route('planning.store'), [
                'title' => 'Saturday island plan',
                'plan_date' => '2026-04-18',
                'start_time_local' => '09:30',
                'speed_knots' => '3.5',
                'launch_name' => 'Reykjavik',
                'launch_lat' => '64.146600',
                'launch_lng' => '-21.942600',
                'landing_name' => 'Grotta',
                'landing_lat' => '64.167200',
                'landing_lng' => '-22.022600',
                'route_waypoints' => json_encode([
                    ['lat' => 64.1531, 'lng' => -21.9782],
                    ['lat' => 64.1595, 'lng' => -22.0043],
                ]),
                'forecast_points' => json_encode([
                    'launch' => [
                        'status' => 'filled',
                        'fields' => [
                            'wind_beaufort' => 4,
                            'tide_state' => 'flooding',
                        ],
                    ],
                ]),
                'notes' => 'Check the headland before committing.',
            ]);

        $plannedSession = PlannedSession::query()
            ->where('profile_id', $profile->id)
            ->where('title', 'Saturday island plan')
            ->firstOrFail();

        $response->assertRedirect(route('planning.edit', $plannedSession));

        $this->assertSame('Reykjavik', $plannedSession->launch_name);
        $this->assertSame('Grotta', $plannedSession->landing_name);
        $this->assertCount(4, $plannedSession->route_profile);
        $this->assertGreaterThan(0, (float) $plannedSession->distance_km);
        $this->assertNotNull($plannedSession->estimated_duration_minutes);
        $this->assertSame(4, $plannedSession->forecast_points['launch']['fields']['wind_beaufort']);
    }

    public function test_saved_plans_can_be_reopened_in_the_planner(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();
        $plannedSession = $profile->plannedSessions()->create([
            'created_by_user_id' => $user->id,
            'title' => 'Saved plan',
            'plan_date' => '2026-04-18',
            'timezone' => $profile->timezone,
            'speed_knots' => 3.5,
            'launch_name' => 'Launch',
            'launch_lat' => 64.1466,
            'launch_lng' => -21.9426,
            'landing_name' => 'Landing',
            'landing_lat' => 64.1672,
            'landing_lng' => -22.0226,
            'route_profile' => [
                ['lat' => 64.1466, 'lng' => -21.9426, 'distanceKm' => 0],
                ['lat' => 64.1531, 'lng' => -21.9782, 'distanceKm' => 1.2],
                ['lat' => 64.1672, 'lng' => -22.0226, 'distanceKm' => 4.8],
            ],
        ]);

        $this->actingAs($user)
            ->get(route('planning.edit', $plannedSession))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('planning/Index')
                ->where('plannedSession.id', $plannedSession->id)
                ->where('plannedSession.title', 'Saved plan')
                ->where('formDefaults.route_waypoints', json_encode([
                    ['lat' => 64.1531, 'lng' => -21.9782],
                ])));
    }
}
