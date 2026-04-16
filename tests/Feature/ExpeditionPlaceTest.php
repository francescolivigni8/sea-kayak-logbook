<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExpeditionPlaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_open_a_private_expedition_place_page(): void
    {
        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();
        $placeSlug = 'isafjordur-'.substr(md5('isafjordur'), 0, 6);

        $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-06',
            'title' => 'Isafjordur camp loop',
            'launch_name' => 'Isafjordur',
            'launch_lat' => 66.0748,
            'launch_lng' => -23.1267,
            'route_category' => 'expedition',
            'distance_km' => 28.3,
            'expedition_days' => 2,
            'is_expedition' => true,
            'is_public' => true,
        ]);

        $this->actingAs($user)
            ->get(route('expeditions.show', ['place' => $placeSlug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('expeditions/Show')
                ->where('place.label', 'Isafjordur'));
    }

    public function test_guests_can_open_a_public_expedition_place_page(): void
    {
        config(['kayak.public_profiles_enabled' => true]);

        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();
        $placeSlug = 'isafjordur-'.substr(md5('isafjordur'), 0, 6);
        $profile->update([
            'is_public' => true,
            'slug' => 'francesco-public-logbook',
        ]);

        $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-06',
            'title' => 'Isafjordur public loop',
            'launch_name' => 'Isafjordur',
            'launch_lat' => 66.0748,
            'launch_lng' => -23.1267,
            'route_category' => 'expedition',
            'distance_km' => 28.3,
            'expedition_days' => 2,
            'is_expedition' => true,
            'is_public' => true,
        ]);

        $this->get(route('profiles.public.expeditions.show', [
            'profile' => $profile,
            'place' => $placeSlug,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('profiles/ExpeditionShow')
                ->where('place.label', 'Isafjordur'));
    }

    public function test_public_expedition_routes_are_disabled_by_default_for_private_launch(): void
    {
        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();
        $placeSlug = 'isafjordur-'.substr(md5('isafjordur'), 0, 6);
        $profile->update([
            'is_public' => true,
            'slug' => 'francesco-public-logbook',
        ]);

        $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-06',
            'title' => 'Isafjordur public loop',
            'launch_name' => 'Isafjordur',
            'launch_lat' => 66.0748,
            'launch_lng' => -23.1267,
            'route_category' => 'expedition',
            'distance_km' => 28.3,
            'expedition_days' => 2,
            'is_expedition' => true,
            'is_public' => true,
        ]);

        $this->get(route('profiles.public.expeditions.show', [
            'profile' => $profile,
            'place' => $placeSlug,
        ]))->assertNotFound();
    }
}
