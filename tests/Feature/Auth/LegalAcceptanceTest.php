<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LegalAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_users_without_current_legal_acceptance_are_redirected_after_login()
    {
        $user = User::factory()->create([
            'accepted_terms_at' => null,
            'accepted_privacy_at' => null,
            'accepted_terms_version' => null,
            'accepted_privacy_version' => null,
        ]);
        $profile = $user->resolveActiveProfile();
        $profile->settings = [];
        $profile->save();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'JournalPass123!',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('legal.acceptance.edit', absolute: false));
    }

    public function test_authenticated_users_without_current_legal_acceptance_are_redirected_from_dashboard()
    {
        $user = User::factory()->create([
            'accepted_terms_at' => null,
            'accepted_privacy_at' => null,
            'accepted_terms_version' => null,
            'accepted_privacy_version' => null,
        ]);
        $profile = $user->resolveActiveProfile();
        $profile->settings = [];
        $profile->save();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('legal.acceptance.edit', absolute: false));
    }

    public function test_authenticated_users_can_open_the_legal_acceptance_screen()
    {
        $this->withoutVite();

        $user = User::factory()->create([
            'accepted_terms_at' => null,
            'accepted_privacy_at' => null,
            'accepted_terms_version' => null,
            'accepted_privacy_version' => null,
        ]);
        $profile = $user->resolveActiveProfile();
        $profile->settings = [];
        $profile->save();

        $response = $this->actingAs($user)->get(route('legal.acceptance.edit'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/LegalAcceptance')
                ->where('termsVersion', config('kayak.legal.terms_version'))
                ->where('privacyVersion', config('kayak.legal.privacy_version'))
                ->where('setupRequired', false));
    }

    public function test_accepting_current_legal_versions_updates_the_user_and_continues()
    {
        $user = User::factory()->create([
            'accepted_terms_at' => null,
            'accepted_privacy_at' => null,
            'accepted_terms_version' => null,
            'accepted_privacy_version' => null,
        ]);
        $profile = $user->resolveActiveProfile();
        $profile->settings = [];
        $profile->save();

        $response = $this->actingAs($user)->patch(route('legal.acceptance.update'), [
            'accept_terms' => '1',
            'accept_privacy' => '1',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'accepted_terms_version' => config('kayak.legal.terms_version'),
            'accepted_privacy_version' => config('kayak.legal.privacy_version'),
        ]);
    }

    public function test_outdated_legal_versions_require_reacceptance()
    {
        $user = User::factory()->create([
            'accepted_terms_at' => now()->subYear(),
            'accepted_privacy_at' => now()->subYear(),
            'accepted_terms_version' => '2025-01-01',
            'accepted_privacy_version' => '2025-01-01',
        ]);
        $profile = $user->resolveActiveProfile();
        $profile->settings = [];
        $profile->save();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('legal.acceptance.edit', absolute: false));
    }
}
