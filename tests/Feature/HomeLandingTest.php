<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomeLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_the_preview_landing_page(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('landing/Preview'));
    }

    public function test_authenticated_users_still_land_on_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertRedirect(route('dashboard'));
    }
}
