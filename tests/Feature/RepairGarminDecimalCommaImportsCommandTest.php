<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairGarminDecimalCommaImportsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_repair_command_dry_runs_without_changing_sessions(): void
    {
        $user = User::factory()->create(['email' => 'paddler@example.com']);
        $profile = $user->resolveActiveProfile();
        $session = $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'external_ref' => 'garmin:2026-04-18 10:03:35',
            'session_date' => '2026-04-18',
            'title' => 'Reykjavik Kayak',
            'route_category' => 'journey',
            'distance_km' => 700.0,
            'duration_minutes' => 101,
            'moving_minutes' => 83,
            'air_temp_c' => 145.0,
            'route_tags' => ['garmin-import', 'spring', 'faxafloi', 'longer-day'],
            'notes_private' => 'Imported from Garmin history.',
        ]);

        $this->artisan('kayak:repair-garmin-decimal-commas', [
            'email' => 'paddler@example.com',
        ])
            ->expectsOutputToContain('Dry run only')
            ->assertSuccessful();

        $this->assertSame(700.0, (float) $session->refresh()->distance_km);
        $this->assertSame(145.0, (float) $session->air_temp_c);
    }

    public function test_repair_command_fixes_decimal_comma_imports_for_one_user(): void
    {
        $user = User::factory()->create(['email' => 'paddler@example.com']);
        $profile = $user->resolveActiveProfile();
        $session = $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'external_ref' => 'garmin:2026-04-18 10:03:35',
            'session_date' => '2026-04-18',
            'title' => 'Reykjavik Kayak',
            'route_category' => 'journey',
            'distance_km' => 700.0,
            'duration_minutes' => 101,
            'moving_minutes' => 83,
            'air_temp_c' => 145.0,
            'route_tags' => ['garmin-import', 'spring', 'faxafloi', 'longer-day'],
            'notes_private' => 'Imported from Garmin history.',
        ]);

        $this->artisan('kayak:repair-garmin-decimal-commas', [
            'email' => 'paddler@example.com',
            '--force' => true,
        ])
            ->expectsOutputToContain('Repaired 1 Garmin sessions.')
            ->assertSuccessful();

        $session->refresh();

        $this->assertSame(7.0, (float) $session->distance_km);
        $this->assertSame(14.5, (float) $session->air_temp_c);
        $this->assertSame('training', $session->route_category);
        $this->assertSame(['garmin-import', 'spring', 'faxafloi', 'short-day'], $session->route_tags);
    }
}
