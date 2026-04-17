<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                ->where('weatherAutofillAvailable', false));
    }

    public function test_planning_weather_preview_returns_waypoint_conditions(): void
    {
        config()->set('kayak.weather.providers.stormglass.api_key', 'test-key');

        Http::fake([
            'api.stormglass.io/v2/weather/point*' => Http::response([
                'hours' => [[
                    'time' => '2026-04-18T09:00:00+00:00',
                    'windSpeed' => ['sg' => 5.8],
                    'gust' => ['sg' => 8.2],
                    'windDirection' => ['sg' => 205],
                    'precipitation' => ['sg' => 0.1],
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
            ->assertJsonPath('fields.wind_direction_deg', 205);
    }
}
