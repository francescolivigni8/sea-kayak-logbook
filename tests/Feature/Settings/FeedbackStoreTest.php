<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_prefills_feedback_context_from_query_string(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit', ['from' => '/planning']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('profile.feedbackContext', '/planning')
                ->where('profile.feedbackUrl', route('feedback.store')));
    }

    public function test_authenticated_user_can_submit_feedback(): void
    {
        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('feedback.store'), [
                'kind' => 'issue',
                'subject' => 'Planning map control overlap',
                'page_context' => 'planning',
                'message' => 'On mobile the top-right controls overlap and become hard to use.',
            ], [
                'referer' => 'https://yourkayakingjournal.com/planning?weather=1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit').'#feedback');

        $this->assertDatabaseHas('feedback_reports', [
            'user_id' => $user->id,
            'profile_id' => $profile->id,
            'kind' => 'issue',
            'subject' => 'Planning map control overlap',
            'page_context' => 'planning',
            'submitted_from_path' => '/planning?weather=1',
            'status' => 'new',
        ]);
    }

    public function test_feedback_requires_valid_input(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('feedback.store'), [
                'kind' => 'complaint',
                'subject' => '',
                'message' => 'short',
            ])
            ->assertSessionHasErrors([
                'kind',
                'subject',
                'message',
            ])
            ->assertRedirect(route('profile.edit'));
    }
}
