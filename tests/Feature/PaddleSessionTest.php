<?php

namespace Tests\Feature;

use App\Models\PaddleSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaddleSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_view_the_sessions_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('sessions.index'))
            ->assertOk();
    }

    public function test_authenticated_users_can_view_a_session_detail_page(): void
    {
        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();
        $session = $profile->sessions()->create([
            'recorded_by_user_id' => $user->id,
            'session_date' => '2026-04-06',
            'title' => 'Faxafloi detail page',
            'launch_name' => 'Reykjavik',
            'route_category' => 'journey',
            'distance_km' => 8.4,
        ]);

        $this->actingAs($user)
            ->get(route('sessions.show', $session))
            ->assertOk()
            ->assertSee('Faxafloi detail page');
    }

    public function test_authenticated_users_can_store_a_paddle_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('sessions.store'), [
                'title' => 'Evening harbor loop',
                'session_date' => '2026-04-06',
                'launch_name' => 'Reykjavik',
                'route_category' => 'journey',
                'distance_km' => '8.4',
                'duration_minutes' => '94',
                'is_public' => true,
            ])
            ->assertRedirect();

        $profile = $user->resolveActiveProfile();

        $this->assertDatabaseHas(PaddleSession::class, [
            'profile_id' => $profile->id,
            'title' => 'Evening harbor loop',
            'launch_name' => 'Reykjavik',
            'route_category' => 'journey',
        ]);
    }

    public function test_manual_sessions_can_store_launch_and_landing_coordinates_without_a_track_file(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('sessions.store'), [
                'title' => 'Manual geolocated paddle',
                'session_date' => '2026-04-06',
                'launch_name' => 'Reykjavik',
                'launch_lat' => '64.146600',
                'launch_lng' => '-21.942600',
                'landing_name' => 'Grotta',
                'landing_lat' => '64.167200',
                'landing_lng' => '-22.022600',
                'route_category' => 'journey',
                'distance_km' => '6.2',
                'is_public' => true,
            ])
            ->assertRedirect();

        $profile = $user->resolveActiveProfile();

        $this->assertDatabaseHas(PaddleSession::class, [
            'profile_id' => $profile->id,
            'title' => 'Manual geolocated paddle',
            'launch_lat' => 64.1466,
            'launch_lng' => -21.9426,
            'landing_lat' => 64.1672,
            'landing_lng' => -22.0226,
        ]);
    }

    public function test_manual_gpx_uploads_are_parsed_into_route_data(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $gpx = UploadedFile::fake()->createWithContent('harbor-loop.gpx', <<<'GPX'
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="test">
  <trk>
    <name>Harbor Loop</name>
    <trkseg>
      <trkpt lat="64.1500" lon="-21.9500"><time>2026-04-06T18:00:00Z</time></trkpt>
      <trkpt lat="64.1510" lon="-21.9400"><time>2026-04-06T18:10:00Z</time></trkpt>
      <trkpt lat="64.1520" lon="-21.9300"><time>2026-04-06T18:20:00Z</time></trkpt>
    </trkseg>
  </trk>
</gpx>
GPX);

        $this->actingAs($user)
            ->post(route('sessions.store'), [
                'title' => 'GPX-backed paddle',
                'session_date' => '2026-04-06',
                'launch_name' => 'Reykjavik',
                'route_category' => 'journey',
                'gpx_file' => $gpx,
                'is_public' => true,
            ])
            ->assertRedirect();

        $profile = $user->resolveActiveProfile();
        $session = PaddleSession::query()
            ->where('profile_id', $profile->id)
            ->where('title', 'GPX-backed paddle')
            ->firstOrFail();

        $this->assertNotNull($session->gpx_path);
        $this->assertNotEmpty($session->route_points);
        $this->assertNotEmpty($session->route_profile);
        $this->assertGreaterThan(0, (float) $session->distance_km);
        $this->assertGreaterThan(0, (int) $session->duration_minutes);
    }

    public function test_manual_fit_uploads_are_parsed_into_track_data(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $fitFixture = file_get_contents(base_path('vendor/adriangibbons/php-fit-file-analysis/demo/fit_files/road-cycling.fit'));
        $this->assertNotFalse($fitFixture);

        $fit = UploadedFile::fake()->createWithContent('road-cycling.fit', $fitFixture);

        $this->actingAs($user)
            ->post(route('sessions.store'), [
                'title' => 'FIT-backed paddle',
                'session_date' => '2026-04-06',
                'launch_name' => 'Reykjavik',
                'route_category' => 'journey',
                'fit_file' => $fit,
                'is_public' => true,
            ])
            ->assertRedirect();

        $profile = $user->resolveActiveProfile();
        $session = PaddleSession::query()
            ->where('profile_id', $profile->id)
            ->where('title', 'FIT-backed paddle')
            ->firstOrFail();

        $this->assertNotNull($session->fit_path);
        $this->assertNotEmpty($session->route_profile);
        $this->assertGreaterThan(0, (float) $session->distance_km);
        $this->assertGreaterThan(0, (int) $session->duration_minutes);
        $this->assertNotNull($session->launch_lat);
        $this->assertNotNull($session->launch_lng);
    }
}
