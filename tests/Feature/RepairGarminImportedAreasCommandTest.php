<?php

namespace Tests\Feature;

use App\Models\PaddleSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RepairGarminImportedAreasCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_repair_command_updates_legacy_garmin_area_from_meaningful_title(): void
    {
        Storage::fake('public');
        config()->set('kayak.media_disk', 'public');

        $user = User::factory()->create(['email' => 'paddler@example.com']);
        $profile = $user->resolveActiveProfile();

        $session = PaddleSession::create([
            'profile_id' => $profile->id,
            'recorded_by_user_id' => $user->id,
            'external_ref' => 'garmin:2025-08-19 00:00:00',
            'session_date' => '2025-08-19',
            'timezone' => 'Atlantic/Reykjavik',
            'title' => 'Reykjavik Kayaking',
            'area_name' => 'Faxafloi',
            'launch_name' => 'Reykjavik',
            'landing_name' => 'Reykjavik',
            'distance_km' => 8.4,
            'duration_minutes' => 85,
            'route_tags' => ['garmin-import', 'summer', 'faxafloi', 'mid-distance'],
        ]);

        $this->artisan('kayak:repair-garmin-areas', [
            '--email' => 'paddler@example.com',
            '--force' => true,
        ])
            ->expectsOutputToContain('Repaired 1 Garmin sessions.')
            ->assertSuccessful();

        $session->refresh();

        $this->assertSame('Reykjavik', $session->area_name);
        $this->assertSame('Reykjavik', $session->launch_name);
        $this->assertSame('Reykjavik', $session->landing_name);
        $this->assertSame(['garmin-import', 'summer', 'reykjavik', 'mid-distance'], $session->route_tags);
    }

    public function test_repair_command_can_recover_area_from_stored_gpx_track_name(): void
    {
        Storage::fake('public');
        config()->set('kayak.media_disk', 'public');

        $user = User::factory()->create(['email' => 'track@example.com']);
        $profile = $user->resolveActiveProfile();

        Storage::disk('public')->put('gpx/imported/'.$profile->slug.'/activity_21245830215.gpx', <<<'GPX'
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="test">
  <trk>
    <name>Reykjanesbaer kayaking</name>
    <trkseg>
      <trkpt lat="63.9980" lon="-22.5560"><time>2026-01-03T10:00:00Z</time></trkpt>
      <trkpt lat="64.0020" lon="-22.5400"><time>2026-01-03T10:30:00Z</time></trkpt>
    </trkseg>
  </trk>
</gpx>
GPX);

        $session = PaddleSession::create([
            'profile_id' => $profile->id,
            'recorded_by_user_id' => $user->id,
            'external_ref' => 'garmin:2026-01-03 00:00:00',
            'session_date' => '2026-01-03',
            'timezone' => 'Atlantic/Reykjavik',
            'title' => 'Faxafloi technical session',
            'area_name' => 'Faxafloi',
            'launch_name' => 'Reykjavik',
            'landing_name' => 'Reykjavik',
            'distance_km' => 5.1,
            'duration_minutes' => 42,
            'route_tags' => ['garmin-import', 'winter', 'faxafloi', 'short-day'],
            'gpx_path' => 'gpx/imported/'.$profile->slug.'/activity_21245830215.gpx',
            'garmin_gpx_name' => 'activity_21245830215.gpx',
        ]);

        $this->artisan('kayak:repair-garmin-areas', [
            '--email' => 'track@example.com',
            '--force' => true,
        ])
            ->expectsOutputToContain('Repaired 1 Garmin sessions.')
            ->assertSuccessful();

        $session->refresh();

        $this->assertSame('Reykjanes', $session->area_name);
        $this->assertSame('Reykjanesbaer', $session->launch_name);
        $this->assertSame('Reykjanesbaer', $session->landing_name);
        $this->assertSame(['garmin-import', 'winter', 'reykjanes', 'short-day'], $session->route_tags);
    }

    public function test_repair_command_can_preview_without_saving(): void
    {
        Storage::fake('public');
        config()->set('kayak.media_disk', 'public');

        $user = User::factory()->create(['email' => 'dryrun@example.com']);
        $profile = $user->resolveActiveProfile();

        $session = PaddleSession::create([
            'profile_id' => $profile->id,
            'recorded_by_user_id' => $user->id,
            'external_ref' => 'garmin:2025-08-21 00:00:00',
            'session_date' => '2025-08-21',
            'timezone' => 'Atlantic/Reykjavik',
            'title' => 'Reykjavik Kayaking',
            'area_name' => 'Faxafloi',
            'launch_name' => 'Reykjavik',
            'landing_name' => 'Reykjavik',
            'distance_km' => 8.4,
            'duration_minutes' => 85,
            'route_tags' => ['garmin-import', 'summer', 'faxafloi', 'mid-distance'],
        ]);

        $this->artisan('kayak:repair-garmin-areas', [
            '--email' => 'dryrun@example.com',
        ])
            ->expectsOutputToContain('Dry run only.')
            ->assertSuccessful();

        $session->refresh();

        $this->assertSame('Faxafloi', $session->area_name);
        $this->assertSame(['garmin-import', 'summer', 'faxafloi', 'mid-distance'], $session->route_tags);
    }
}
