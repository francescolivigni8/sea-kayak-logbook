<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Features;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_legacy_profiles_do_not_enter_setup_mode_from_query_string()
    {
        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();
        $profile->settings = [];
        $profile->save();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit', ['setup' => 1]));

        $response
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('requiresSetup', false)
                ->where('setupMode', false));
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();
        $profile->settings = [];
        $profile->save();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'paddler_name' => 'Francesco Li Vigni',
                'kayak_club' => 'Brokey Kayak Club',
                'kayaks_owned_text' => 'Valley Etain 17-7, P&H Scorpio MV',
                'paddles_owned_text' => 'Werner Cyprus, Gearlab Kalleq',
                'default_map_lat' => '65.688500',
                'default_map_lng' => '-18.126200',
                'default_map_zoom' => '12',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();
        $profile = $user->resolveActiveProfile()->fresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('Francesco Li Vigni', data_get($profile->settings, 'paddler_name'));
        $this->assertSame('Brokey Kayak Club', data_get($profile->settings, 'kayak_club'));
        $this->assertSame(['Valley Etain 17-7', 'P&H Scorpio MV'], data_get($profile->settings, 'kayaks_owned'));
        $this->assertSame(['Werner Cyprus', 'Gearlab Kalleq'], data_get($profile->settings, 'paddles_owned'));
        $this->assertSame(65.6885, data_get($profile->settings, 'default_map_view.lat'));
        $this->assertSame(-18.1262, data_get($profile->settings, 'default_map_view.lng'));
        $this->assertSame(12, data_get($profile->settings, 'default_map_view.zoom'));
        $this->assertFalse(data_get($profile->settings, 'setup_required'));
        $this->assertNotNull(data_get($profile->settings, 'setup_completed_at'));
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();
        $profile->settings = [];
        $profile->save();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_changing_email_resets_verification_when_email_verification_is_enabled(): void
    {
        config()->set('fortify.features', [
            Features::registration(),
            Features::resetPasswords(),
            Features::emailVerification(),
        ]);

        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();
        $profile->settings = ['setup_required' => false];
        $profile->save();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'new-address@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'JournalPass123!',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_user_account_deletion_removes_owned_session_media()
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();
        $session = $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-16',
            'title' => 'Media cleanup test',
            'launch_name' => 'Reykjavik',
            'route_category' => 'journey',
            'distance_km' => 4.2,
            'duration_minutes' => 50,
            'gpx_path' => 'gpx/manual/test-route.gpx',
            'fit_path' => 'fit/manual/test-route.fit',
            'session_photo_path' => 'session-photos/test-route.jpg',
        ]);

        collect([
            $session->gpx_path,
            $session->fit_path,
            $session->session_photo_path,
        ])->each(fn (string $path) => Storage::disk('public')->put($path, 'test'));

        $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'JournalPass123!',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        Storage::disk('public')->assertMissing('gpx/manual/test-route.gpx');
        Storage::disk('public')->assertMissing('fit/manual/test-route.fit');
        Storage::disk('public')->assertMissing('session-photos/test-route.jpg');
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }
}
