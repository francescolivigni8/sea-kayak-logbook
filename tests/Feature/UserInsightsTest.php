<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserInsightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_owner_users_cannot_view_the_user_insights_page(): void
    {
        config()->set('kayak.owner_emails', ['owner@example.com']);

        $user = User::factory()->create(['email' => 'member@example.com']);

        $this->actingAs($user)
            ->get(route('insights.users'))
            ->assertForbidden();
    }

    public function test_owner_can_view_user_insights_with_funnel_and_user_metrics(): void
    {
        config()->set('kayak.owner_emails', ['owner@example.com']);

        $owner = User::factory()->create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'created_at' => now()->subDays(2),
        ]);
        $ownerProfile = $owner->resolveActiveProfile();
        $ownerSettings = $ownerProfile->settings ?? [];
        $ownerSettings['setup_completed_at'] = now()->subDay()->toIso8601String();
        $ownerProfile->settings = $ownerSettings;
        $ownerProfile->save();
        $ownerProfile->sessions()->create([
            'recorded_by_user_id' => $owner->id,
            'external_ref' => 'garmin:1',
            'session_date' => now()->toDateString(),
            'title' => 'Owner benchmark',
            'launch_name' => 'Reykjavik',
            'route_category' => 'benchmark',
            'distance_km' => 8.2,
            'duration_minutes' => 95,
            'notes_public' => 'Strong benchmark effort.',
        ]);

        $setupUser = User::factory()->create(['email' => 'setup@example.com', 'created_at' => now()->subDays(10)]);
        $setupProfile = $setupUser->resolveActiveProfile();
        $setupSettings = $setupProfile->settings ?? [];
        $setupSettings['setup_completed_at'] = now()->subDays(9)->toIso8601String();
        $setupProfile->settings = $setupSettings;
        $setupProfile->save();
        $setupProfile->sessions()->create([
            'recorded_by_user_id' => $setupUser->id,
            'session_date' => now()->subDays(5)->toDateString(),
            'title' => 'Manual harbor loop',
            'launch_name' => 'Harbor',
            'route_category' => 'journey',
            'distance_km' => 5.1,
            'duration_minutes' => 70,
        ]);

        $newUser = User::factory()->create(['email' => 'new@example.com', 'created_at' => now()->subDays(1)]);
        $newUser->resolveActiveProfile();

        $this->actingAs($owner)
            ->get(route('insights.users'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('insights/Users')
                ->has('overviewCards', 6)
                ->has('healthCards', 4)
                ->has('funnel', 5)
                ->has('users', 3)
                ->where('overviewCards.0.label', 'Total users')
                ->where('overviewCards.0.value', 3)
                ->where('overviewCards.2.label', 'Active in 30 days')
                ->where('overviewCards.2.value', 3)
                ->where('overviewCards.4.label', 'Logged a session')
                ->where('overviewCards.4.value', 2)
                ->where('overviewCards.5.label', 'Added observations')
                ->where('overviewCards.5.value', 1)
                ->where('healthCards.0.label', 'Garmin import users')
                ->where('healthCards.0.value', 1)
                ->where('funnel.0.count', 3)
                ->where('funnel.1.count', 2)
                ->where('funnel.2.count', 2)
                ->where('funnel.3.count', 1)
                ->where('funnel.4.count', 1)
                ->where('users.0.name', 'Owner User Sea Kayak Logbook')
                ->where('users.0.sessionCount', 1)
                ->where('users.0.importedSessionCount', 1));
    }
}
