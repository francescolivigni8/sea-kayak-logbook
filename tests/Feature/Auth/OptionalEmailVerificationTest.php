<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class OptionalEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_users_can_open_journal_when_email_verification_is_disabled(): void
    {
        $this->disableEmailVerification();

        $user = User::factory()->unverified()->create();
        $user->resolveActiveProfile()->forceFill([
            'settings' => ['setup_required' => false],
        ])->save();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_registration_marks_new_users_verified_when_verification_is_disabled(): void
    {
        $this->disableEmailVerification();

        $this->post(route('register.store'), [
            'name' => 'Staging Tester',
            'email' => 'staging@example.com',
            'password' => 'JournalPass123!',
            'password_confirmation' => 'JournalPass123!',
            'accept_terms' => '1',
            'accept_privacy' => '1',
        ])->assertRedirect(route('profile.edit', ['setup' => 1], false));

        $user = User::where('email', 'staging@example.com')->firstOrFail();

        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    private function disableEmailVerification(): void
    {
        config()->set('fortify.features', array_values(array_filter(
            config('fortify.features', []),
            fn (string $feature) => $feature !== Features::emailVerification(),
        )));
    }
}
