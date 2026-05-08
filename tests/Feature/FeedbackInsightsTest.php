<?php

namespace Tests\Feature;

use App\Models\FeedbackReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FeedbackInsightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_owner_users_cannot_view_feedback_insights(): void
    {
        config()->set('kayak.owner_emails', ['owner@example.com']);

        $user = User::factory()->create(['email' => 'member@example.com']);

        $this->actingAs($user)
            ->get(route('insights.feedback'))
            ->assertForbidden();
    }

    public function test_owner_can_view_feedback_inbox(): void
    {
        config()->set('kayak.owner_emails', ['owner@example.com']);

        $owner = User::factory()->create([
            'email' => 'owner@example.com',
        ]);
        $reporter = User::factory()->create([
            'name' => 'Tester Name',
            'email' => 'tester@example.com',
        ]);
        $profile = $reporter->resolveActiveProfile();

        FeedbackReport::create([
            'user_id' => $reporter->id,
            'profile_id' => $profile->id,
            'kind' => 'issue',
            'subject' => 'Planning map overlap',
            'page_context' => 'planning',
            'message' => 'The control cluster overlaps on mobile.',
            'submitted_from_path' => '/planning?weather=1',
            'status' => 'new',
        ]);

        $this->actingAs($owner)
            ->get(route('insights.feedback'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('insights/Feedback')
                ->has('overviewCards', 4)
                ->has('kindBreakdown', 4)
                ->has('reports', 1)
                ->where('reports.0.subject', 'Planning map overlap')
                ->where('reports.0.kind', 'issue')
                ->where('reports.0.user.email', 'tester@example.com')
                ->where('reports.0.pageContext', 'planning'));
    }
}
