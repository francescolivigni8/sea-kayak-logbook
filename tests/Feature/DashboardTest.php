<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_dashboard_includes_expedition_summary_and_world_map_data(): void
    {
        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();

        $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-06',
            'title' => 'Westfjord multiday',
            'launch_name' => 'Isafjordur',
            'launch_lat' => 66.0748,
            'launch_lng' => -23.1267,
            'route_category' => 'expedition',
            'distance_km' => 42.5,
            'moving_minutes' => 180,
            'duration_minutes' => 210,
            'wind_beaufort' => 4,
            'expedition_days' => 3,
            'is_expedition' => true,
            'is_public' => true,
        ]);

        $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-08',
            'title' => 'Westfjord second push',
            'launch_name' => 'Isafjordur',
            'launch_lat' => 66.0748,
            'launch_lng' => -23.1267,
            'route_category' => 'expedition',
            'distance_km' => 18.0,
            'moving_minutes' => 120,
            'duration_minutes' => 130,
            'wind_beaufort' => 5,
            'expedition_days' => 2,
            'is_expedition' => true,
            'is_public' => true,
        ]);

        $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-09',
            'title' => 'Unmapped expedition note',
            'launch_name' => 'Unknown cove',
            'route_category' => 'expedition',
            'distance_km' => 6.0,
            'duration_minutes' => 120,
            'is_expedition' => true,
            'is_public' => true,
        ]);

        $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-10',
            'title' => 'Regular Isafjordur paddle',
            'launch_name' => 'Isafjordur',
            'launch_lat' => 66.0748,
            'launch_lng' => -23.1267,
            'route_category' => 'journey',
            'distance_km' => 8.0,
            'duration_minutes' => 90,
            'is_expedition' => false,
        ]);

        $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-11',
            'title' => 'Regular Reykjavik paddle',
            'launch_name' => 'Reykjavik',
            'launch_lat' => 64.1466,
            'launch_lng' => -21.9426,
            'route_category' => 'journey',
            'distance_km' => 7.0,
            'duration_minutes' => 80,
            'is_expedition' => false,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('headline.averageSpeedKnots', 4)
                ->where('headline.averageSpeedSamples', 5)
                ->where('seaState.averageBeaufort', 4.5)
                ->where('expeditionSummary.distanceKm', 66.5)
                ->where('expeditionSummary.daysOut', 5)
                ->where('expeditionSummary.tripCount', 3)
                ->where('expeditionSummary.missingMapPointCount', 1)
                ->has('expeditionMapData.pins', 2)
                ->has('expeditionSessionLinks', 3)
                ->where('expeditionSessionLinks.0.label', '09 Apr 2026 · Unmapped expedition note')
                ->has('expeditionPlaces', 1));
    }

    public function test_footprint_map_groups_repeated_places_and_includes_day_sessions(): void
    {
        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();

        foreach (range(1, 3) as $index) {
            $profile->sessions()->create([
                'recorded_by_user_id' => $user->id,
                'session_date' => now()->subDays($index)->toDateString(),
                'title' => 'Harbor paddle '.$index,
                'launch_name' => 'Reykjavik',
                'launch_lat' => 64.1466,
                'launch_lng' => -21.9426,
                'route_category' => 'journey',
                'distance_km' => 5.0,
                'duration_minutes' => 60,
                'is_expedition' => false,
            ]);
        }

        $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => now()->subDays(4)->toDateString(),
            'title' => 'Reykjanes paddle',
            'launch_name' => 'Reykjanes',
            'launch_lat' => 63.999,
            'launch_lng' => -22.56,
            'route_category' => 'journey',
            'distance_km' => 9.0,
            'duration_minutes' => 110,
            'is_expedition' => false,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('expeditionMapData.pins', 2)
                ->where('expeditionMapData.pins.0.count', 3)
                ->where('expeditionMapData.pins.1.count', 1));
    }

    public function test_dashboard_route_map_includes_all_tracked_sessions(): void
    {
        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();

        foreach (range(1, 10) as $index) {
            $profile->sessions()->create([
                'recorded_by_user_id' => $user->id,
                'session_date' => now()->subDays($index)->toDateString(),
                'title' => 'Tracked paddle '.$index,
                'launch_lat' => 64.1 + ($index / 100),
                'launch_lng' => -21.9,
                'landing_lat' => 64.11 + ($index / 100),
                'landing_lng' => -21.88,
                'distance_km' => 5.0,
                'duration_minutes' => 60,
                'route_profile' => [
                    [
                        'lat' => 64.1 + ($index / 100),
                        'lng' => -21.9,
                    ],
                    [
                        'lat' => 64.11 + ($index / 100),
                        'lng' => -21.88,
                    ],
                ],
            ]);
        }

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('mapData.routes', 10));
    }
}
