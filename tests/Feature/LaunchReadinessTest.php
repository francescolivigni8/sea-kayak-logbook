<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SessionMediaService;
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

    public function test_security_headers_are_sent(): void
    {
        config(['session.secure' => true]);

        $this->get(route('login'))
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
            ->assertHeader('Content-Security-Policy-Report-Only');
    }

    public function test_private_media_urls_fail_closed_without_temporary_urls(): void
    {
        config([
            'kayak.media_disk' => 's3',
            'kayak.media_temporary_urls' => false,
        ]);

        $this->assertNull(app(SessionMediaService::class)->url('session-photos/private.jpg'));
    }

    public function test_user_can_export_owned_journal_data(): void
    {
        $user = User::factory()->create([
            'name' => 'Francesco Li Vigni',
            'email' => 'francesco@example.com',
        ]);
        $profile = $user->resolveActiveProfile();
        $category = $profile->sessionCategories()->create([
            'name' => 'Anglesey',
            'slug' => 'anglesey',
        ]);
        $session = $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-16',
            'title' => 'North Stack lap',
            'launch_name' => 'Holyhead',
            'launch_lat' => 53.309,
            'launch_lng' => -4.633,
            'route_category' => 'journey',
            'distance_km' => 12.4,
            'duration_minutes' => 180,
            'notes_public' => 'Good tidal planning lesson.',
        ]);
        $session->categories()->attach($category);
        $profile->plannedSessions()->create([
            'created_by_user_id' => $user->id,
            'status' => 'draft',
            'plan_date' => '2026-04-18',
            'timezone' => 'Atlantic/Reykjavik',
            'title' => 'Practice plan',
            'distance_km' => 5.5,
            'estimated_duration_minutes' => 90,
            'speed_knots' => 3.2,
        ]);

        $response = $this->actingAs($user)->get(route('profile.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json; charset=utf-8');

        $payload = json_decode($response->streamedContent(), true);

        $this->assertSame('Francesco Li Vigni', $payload['user']['name']);
        $this->assertSame('North Stack lap', $payload['profiles'][0]['sessions'][0]['title']);
        $this->assertSame('Anglesey', $payload['profiles'][0]['session_categories'][0]['name']);
        $this->assertSame('Practice plan', $payload['profiles'][0]['planned_sessions'][0]['title']);
    }

    public function test_basic_legal_pages_are_available(): void
    {
        $this->get(route('legal.privacy'))->assertOk();
        $this->get(route('legal.terms'))->assertOk();
        $this->get(route('legal.contact'))->assertOk();
    }
}
