<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DiaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_open_the_diary(): void
    {
        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();

        $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-06',
            'title' => 'Harbor diary test',
            'launch_name' => 'Reykjavik',
            'route_category' => 'journey',
            'distance_km' => 9.2,
            'duration_minutes' => 90,
            'is_public' => true,
        ]);

        $this->actingAs($user)
            ->get(route('diary'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('diary/Index')
                ->where('stats.sessionCount', 1)
                ->where('stats.paddledDays', 1)
                ->has('entries', 1));
    }
}
