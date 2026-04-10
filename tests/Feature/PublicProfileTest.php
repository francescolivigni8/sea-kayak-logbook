<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_view_a_public_profile(): void
    {
        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();
        $profile->update([
            'is_public' => true,
            'slug' => 'francesco-public-logbook',
        ]);

        $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-06',
            'title' => 'Public harbor loop',
            'launch_name' => 'Reykjavik',
            'route_category' => 'journey',
            'distance_km' => 8.4,
            'duration_minutes' => 120,
            'is_public' => true,
        ]);

        $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-07',
            'title' => 'Public expedition',
            'launch_name' => 'Isafjordur',
            'launch_lat' => 66.0748,
            'launch_lng' => -23.1267,
            'route_category' => 'expedition',
            'distance_km' => 32.2,
            'moving_minutes' => 300,
            'duration_minutes' => 320,
            'expedition_days' => 2,
            'is_expedition' => true,
            'is_public' => true,
        ]);

        $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-08',
            'title' => 'Public expedition second day',
            'launch_name' => 'Isafjordur',
            'launch_lat' => 66.0748,
            'launch_lng' => -23.1267,
            'route_category' => 'expedition',
            'distance_km' => 14.3,
            'duration_minutes' => 120,
            'expedition_days' => 1,
            'is_expedition' => true,
            'is_public' => true,
        ]);

        $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-09',
            'title' => 'Public expedition without map point',
            'launch_name' => 'Loose note only',
            'route_category' => 'expedition',
            'distance_km' => 4.5,
            'duration_minutes' => 60,
            'is_expedition' => true,
            'is_public' => true,
        ]);

        $this->get(route('profiles.public.show', $profile))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('profiles/PublicShow')
                ->where('profile.slug', 'francesco-public-logbook')
                ->where('headline.averageSpeedKnots', 3)
                ->where('headline.averageSpeedSamples', 4)
                ->where('expeditionSummary.tripCount', 3)
                ->where('expeditionSummary.missingMapPointCount', 1)
                ->has('expeditionMapData.pins', 2)
                ->has('expeditionPlaces', 1));
    }

    public function test_private_profiles_are_not_exposed_publicly(): void
    {
        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();
        $profile->update([
            'is_public' => false,
            'slug' => 'francesco-private-logbook',
        ]);

        $this->get(route('profiles.public.show', $profile))
            ->assertNotFound();
    }
}
