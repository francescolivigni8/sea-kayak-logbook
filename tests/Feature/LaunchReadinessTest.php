<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaunchReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_profiles_are_private_by_default(): void
    {
        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();

        $this->assertFalse($profile->is_public);
    }

    public function test_new_sessions_are_private_by_default(): void
    {
        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();

        $session = $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-16',
            'title' => 'Private launch session',
            'launch_name' => 'Reykjavik',
            'route_category' => 'journey',
            'distance_km' => 3.2,
            'duration_minutes' => 45,
        ]);

        $this->assertFalse($session->refresh()->is_public);
    }

    public function test_noindex_header_is_enabled_by_default(): void
    {
        $this->get(route('login'))
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_basic_legal_pages_are_available(): void
    {
        $this->get(route('legal.privacy'))->assertOk();
        $this->get(route('legal.terms'))->assertOk();
        $this->get(route('legal.contact'))->assertOk();
    }
}
