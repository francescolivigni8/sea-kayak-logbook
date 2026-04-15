<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class JournalNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_open_observations(): void
    {
        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();

        $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-06',
            'title' => 'Observation test paddle',
            'launch_name' => 'Reykjavik',
            'route_category' => 'benchmark',
            'distance_km' => 7.1,
            'notes_public' => 'A clean benchmark note.',
            'is_public' => true,
        ]);

        $this->actingAs($user)
            ->get(route('observations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('notes/Index')
                ->where('mode', 'observations')
                ->where('count', 1)
                ->has('items', 1));
    }

    public function test_observations_hide_sessions_without_observation_text(): void
    {
        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();

        $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-06',
            'title' => 'Has observation',
            'launch_name' => 'Reykjavik',
            'route_category' => 'benchmark',
            'distance_km' => 7.1,
            'notes_public' => 'Good lesson from this paddle.',
            'is_public' => true,
        ]);

        $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-07',
            'title' => 'No observation text',
            'launch_name' => 'Reykjavik',
            'route_category' => 'journey',
            'distance_km' => 5.4,
            'is_public' => true,
        ]);

        $this->actingAs($user)
            ->get(route('observations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('notes/Index')
                ->where('count', 1)
                ->has('items', 1)
                ->where('items.0.title', 'Has observation')
                ->where('items.0.summary', 'Good lesson from this paddle.'));
    }

    public function test_authenticated_users_can_open_expedition_notes(): void
    {
        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();

        $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-06',
            'title' => 'Expedition note test',
            'launch_name' => 'Isafjordur',
            'route_category' => 'expedition',
            'distance_km' => 21.4,
            'expedition_notes' => 'Pack less fresh food on day three.',
            'is_expedition' => true,
            'expedition_days' => 3,
            'is_public' => true,
        ]);

        $this->actingAs($user)
            ->get(route('expedition-notes'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('notes/Index')
                ->where('mode', 'expedition-notes')
                ->where('count', 1)
                ->has('items', 1));
    }
}
