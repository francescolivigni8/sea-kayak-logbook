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

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('headline.averageSpeedKnots', 4.7)
                ->where('headline.averageSpeedSamples', 3)
                ->where('expeditionSummary.distanceKm', 66.5)
                ->where('expeditionSummary.daysOut', 5)
                ->where('expeditionSummary.tripCount', 3)
                ->where('expeditionSummary.missingMapPointCount', 1)
                ->has('expeditionMapData.pins', 2)
                ->has('expeditionPlaces', 1));
    }

}
