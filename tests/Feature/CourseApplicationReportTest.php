<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CourseApplicationReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_includes_generate_report_entry_point(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();
        $settings = $profile->settings ?? [];
        $settings['paddler_name'] = 'Francesco Li Vigni';
        $settings['kayak_club'] = 'Brokey Kayak Club';
        $settings['kayaks_owned'] = ['Valley Etain 17-7'];
        $settings['paddles_owned'] = ['Werner Cyprus'];
        $profile->settings = $settings;
        $profile->save();

        $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-06',
            'title' => 'Anglesey assessment paddle',
            'launch_name' => 'Trearddur Bay',
            'route_category' => 'journey',
            'distance_km' => 16.4,
            'duration_minutes' => 180,
            'wind_beaufort' => 4,
            'notes_public' => 'Useful benchmark before applying for the next course.',
            'skills' => ['navigation', 'decision-making'],
            'conditions_logged' => true,
            'development_logged' => true,
        ]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Profile')
                ->where('profile.reportUrl', route('profile.report')));
    }

    public function test_authenticated_users_can_open_the_profile_report_print_view(): void
    {
        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();

        $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-06',
            'title' => 'Advanced assessment day',
            'launch_name' => 'Reykjavik',
            'route_category' => 'training',
            'distance_km' => 14.2,
            'duration_minutes' => 155,
            'wind_beaufort' => 5,
            'notes_public' => 'Handled tide planning and group decisions well.',
        ]);

        $this->actingAs($user)
            ->get(route('profile.report'))
            ->assertOk()
            ->assertViewIs('courses.report')
            ->assertSee('Advanced sea kayak course application')
            ->assertSee('Advanced assessment day')
            ->assertSee('Full session log');
    }
}
