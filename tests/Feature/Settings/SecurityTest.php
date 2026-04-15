<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_shortcut_redirects_to_the_profile_settings_page()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('security.edit'))
            ->assertRedirect(route('profile.edit').'#security');
    }

    public function test_password_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'JournalPass123!',
                'password' => 'NewJournalPass123!',
                'password_confirmation' => 'NewJournalPass123!',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue(Hash::check('NewJournalPass123!', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'NewJournalPass123!',
                'password_confirmation' => 'NewJournalPass123!',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect(route('profile.edit'));
    }
}
