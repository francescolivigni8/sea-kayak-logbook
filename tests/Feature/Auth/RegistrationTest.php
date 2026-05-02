<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'JournalPass123!',
            'password_confirmation' => 'JournalPass123!',
            'accept_terms' => '1',
            'accept_privacy' => '1',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('profile.edit', ['setup' => 1], false));
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'accepted_terms_version' => config('kayak.legal.terms_version'),
            'accepted_privacy_version' => config('kayak.legal.privacy_version'),
        ]);
    }

    public function test_new_users_must_accept_terms_and_privacy_to_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'JournalPass123!',
            'password_confirmation' => 'JournalPass123!',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors([
            'accept_terms',
            'accept_privacy',
        ]);
        $this->assertDatabaseMissing('users', [
            'email' => 'test@example.com',
        ]);
    }

    public function test_invite_only_registration_blocks_uninvited_emails()
    {
        config()->set('kayak.invite_only', true);
        config()->set('kayak.invite_emails', ['invited@example.com']);

        $response = $this->post(route('register.store'), [
            'name' => 'Uninvited User',
            'email' => 'uninvited@example.com',
            'password' => 'JournalPass123!',
            'password_confirmation' => 'JournalPass123!',
            'accept_terms' => '1',
            'accept_privacy' => '1',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', [
            'email' => 'uninvited@example.com',
        ]);
    }

    public function test_invite_only_registration_allows_invited_emails()
    {
        config()->set('kayak.invite_only', true);
        config()->set('kayak.invite_emails', ['invited@example.com']);

        $response = $this->post(route('register.store'), [
            'name' => 'Invited User',
            'email' => 'Invited@Example.com',
            'password' => 'JournalPass123!',
            'password_confirmation' => 'JournalPass123!',
            'accept_terms' => '1',
            'accept_privacy' => '1',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('profile.edit', ['setup' => 1], false));
        $this->assertDatabaseHas('users', [
            'email' => 'invited@example.com',
        ]);
    }
}
