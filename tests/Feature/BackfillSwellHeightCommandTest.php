<?php

namespace Tests\Feature;

use App\Models\PaddleSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BackfillSwellHeightCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_swell_height_command_fills_missing_swell_from_stormglass(): void
    {
        Cache::flush();
        config()->set('kayak.weather.providers.stormglass.api_key', 'test-key');
        config()->set('kayak.weather.providers.open_meteo.enabled', true);

        Http::fake([
            'api.stormglass.io/v2/weather/point*' => Http::response([
                'hours' => [[
                    'time' => '2026-04-06T18:00:00+00:00',
                    'windSpeed' => ['sg' => 7.2],
                    'gust' => ['sg' => 10.4],
                    'windDirection' => ['sg' => 215],
                    'currentSpeed' => ['sg' => 0.4],
                    'currentDirection' => ['sg' => 155],
                    'waveHeight' => ['sg' => 0.7],
                    'swellHeight' => ['sg' => 0.9],
                    'swellPeriod' => ['sg' => 5.5],
                    'swellDirection' => ['sg' => 235],
                    'airTemperature' => ['sg' => 8.5],
                    'waterTemperature' => ['sg' => 6.1],
                    'visibility' => ['sg' => 12000],
                ]],
            ]),
            'api.stormglass.io/v2/tide/extremes/point*' => Http::response([
                'data' => [],
            ]),
        ]);

        $user = User::factory()->create(['email' => 'paddler@example.com']);
        $profile = $user->resolveActiveProfile();

        $session = PaddleSession::create([
            'profile_id' => $profile->id,
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-06',
            'start_at' => Carbon::parse('2026-04-06 18:00:00', 'Atlantic/Reykjavik'),
            'timezone' => 'Atlantic/Reykjavik',
            'title' => 'Missing swell',
            'launch_lat' => 64.1466,
            'launch_lng' => -21.9426,
            'distance_km' => 8.4,
            'duration_minutes' => 90,
        ]);

        $this->artisan('kayak:backfill-swell-height', [
            '--email' => 'paddler@example.com',
        ])
            ->expectsOutputToContain('Updated 1 sessions.')
            ->assertSuccessful();

        $session->refresh();

        $this->assertSame(0.9, (float) $session->swell_height_m);
        $this->assertSame(5.5, (float) $session->swell_period_s);
        $this->assertSame(235, (int) $session->swell_direction_deg);
        $this->assertTrue((bool) $session->conditions_logged);
    }

    public function test_backfill_swell_height_command_uses_open_meteo_when_stormglass_misses_swell(): void
    {
        Cache::flush();
        config()->set('kayak.weather.providers.stormglass.api_key', 'test-key');
        config()->set('kayak.weather.providers.open_meteo.enabled', true);

        Http::fake([
            'api.stormglass.io/v2/weather/point*' => Http::response([
                'hours' => [[
                    'time' => '2026-04-18T09:00:00+00:00',
                    'windSpeed' => ['sg' => 5.8],
                    'gust' => ['sg' => 8.2],
                    'windDirection' => ['sg' => 205],
                    'airTemperature' => ['sg' => 9.5],
                    'visibility' => ['sg' => 9000],
                ]],
            ]),
            'api.stormglass.io/v2/tide/extremes/point*' => Http::response([
                'data' => [],
            ]),
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
                    'wave_height' => [0.4, 0.6, 0.7],
                    'swell_wave_height' => [0.5, 0.8, 0.9],
                    'swell_wave_period' => [4.2, 5.1, 5.4],
                    'swell_wave_direction' => [220, 230, 235],
                    'sea_surface_temperature' => [6.2, 6.4, 6.5],
                    'ocean_current_velocity' => [1.0, 1.3, 1.2],
                    'ocean_current_direction' => [140, 145, 150],
                    'sea_level_height_msl' => [1.0, 1.2, 1.4],
                ],
            ]),
        ]);

        $user = User::factory()->create(['email' => 'fallback@example.com']);
        $profile = $user->resolveActiveProfile();

        $session = PaddleSession::create([
            'profile_id' => $profile->id,
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-18',
            'start_at' => Carbon::parse('2026-04-18 09:00:00', 'Atlantic/Reykjavik'),
            'timezone' => 'Atlantic/Reykjavik',
            'title' => 'Fallback swell',
            'launch_lat' => 64.1466,
            'launch_lng' => -21.9426,
            'distance_km' => 7.2,
            'duration_minutes' => 80,
        ]);

        $this->artisan('kayak:backfill-swell-height', [
            '--email' => 'fallback@example.com',
        ])
            ->expectsOutputToContain('Updated 1 sessions.')
            ->assertSuccessful();

        $session->refresh();

        $this->assertSame(0.8, (float) $session->swell_height_m);
        $this->assertSame(5.1, (float) $session->swell_period_s);
        $this->assertSame(230, (int) $session->swell_direction_deg);
        $this->assertTrue((bool) $session->conditions_logged);
    }
}
