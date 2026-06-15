<?php

namespace Tests\Feature;

use App\Models\PaddleSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GarminImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_view_the_garmin_import_screen(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('imports.garmin.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('imports/Garmin'));
    }

    public function test_authenticated_users_can_import_garmin_csv_history(): void
    {
        $user = User::factory()->create();

        $csv = UploadedFile::fake()->createWithContent('activities.csv', implode("\n", [
            'Date,Title,Activity Type,Distance,Moving Time,Elapsed Time,Min Temp,Max Temp',
            '2026-04-01 18:15:00,Reykjavik Kayaking,Kayaking,8.4,01:20:00,01:25:00,5,8',
        ]));

        $this->actingAs($user)
            ->post(route('imports.garmin.store'), [
                'csv_file' => $csv,
            ])
            ->assertRedirect(route('sessions.index'));

        $profile = $user->resolveActiveProfile();
        $session = PaddleSession::query()
            ->where('profile_id', $profile->id)
            ->where('title', 'Reykjavik Kayaking')
            ->firstOrFail();

        $this->assertDatabaseHas(PaddleSession::class, [
            'profile_id' => $profile->id,
            'title' => 'Reykjavik Kayaking',
            'distance_km' => 8.4,
        ]);
        $this->assertSame('Reykjavik', $session->area_name);
        $this->assertSame('Reykjavik', $session->launch_name);
    }

    public function test_garmin_import_does_not_default_unknown_areas_to_faxafloi(): void
    {
        $user = User::factory()->create();

        $csv = UploadedFile::fake()->createWithContent('activities.csv', implode("\n", [
            'Date,Title,Activity Type,Distance,Moving Time,Elapsed Time,Min Temp,Max Temp',
            '2026-04-01 18:15:00,Kayaking,Kayaking,8.4,01:20:00,01:25:00,5,8',
        ]));

        $this->actingAs($user)
            ->post(route('imports.garmin.store'), [
                'csv_file' => $csv,
            ])
            ->assertRedirect(route('sessions.index'));

        $profile = $user->resolveActiveProfile();
        $session = PaddleSession::query()
            ->where('profile_id', $profile->id)
            ->where('title', 'Imported paddle')
            ->firstOrFail();

        $this->assertNull($session->area_name);
        $this->assertNull($session->launch_name);
        $this->assertNull($session->landing_name);
    }

    public function test_garmin_import_handles_decimal_comma_exports(): void
    {
        $user = User::factory()->create();

        $csv = UploadedFile::fake()->createWithContent('activities-decimal-comma.csv', implode("\n", [
            'Activity Type,Date,Favorite,Title,Distance,Calories,Time,Avg HR,Max HR,Aerobic TE,Avg Pace,Best Pace,Total Ascent,Total Descent,Training Stress Score®,Body Battery Drain,Min Temp,Decompression,Best Lap Time,Number of Laps,Max Temp,Moving Time,Elapsed Time,Min Elevation,Max Elevation',
            'Kayaking,2026-04-18 10:03:35,false,"Reykjavik Kayak","7,00","315","01:40:55","83","108","0,2","14:26","8:11","28","50","0,0","--","9,0","No","01:40:55","1","20,0","01:22:59","01:41:01","-120","-67"',
        ]));

        $this->actingAs($user)
            ->post(route('imports.garmin.store'), [
                'csv_file' => $csv,
            ])
            ->assertRedirect(route('sessions.index'));

        $profile = $user->resolveActiveProfile();
        $session = PaddleSession::query()
            ->where('profile_id', $profile->id)
            ->where('title', 'Reykjavik Kayak')
            ->firstOrFail();

        $this->assertSame(7.0, (float) $session->distance_km);
        $this->assertSame(101, (int) $session->duration_minutes);
        $this->assertSame(83, (int) $session->moving_minutes);
        $this->assertSame(14.5, (float) $session->air_temp_c);
    }

    public function test_garmin_import_handles_semicolon_activity_exports(): void
    {
        $user = User::factory()->create();

        $csv = UploadedFile::fake()->createWithContent('activities-semicolon.csv', implode("\n", [
            'Activity Type;Date;Favorite;Title;Distance;Calories;Time;Avg HR;Max HR;Aerobic TE;Avg Pace;Best Pace;Total Ascent;Total Descent;Training Stress Score®;Body Battery Drain;Min Temp;Decompression;Best Lap Time;Number of Laps;Max Temp;Moving Time;Elapsed Time;Min Elevation;Max Elevation',
            'Kayaking;2026-04-18 10:03:35;false;"Reykjavik Kayak";"7,00";"315";"01:40:55";"83";"108";"0,2";"14:26";"8:11";"28";"50";"0,0";"--";"9,0";"No";"01:40:55";"1";"20,0";"01:22:59";"01:41:01";"-120";"-67"',
        ]));

        $this->actingAs($user)
            ->post(route('imports.garmin.store'), [
                'csv_file' => $csv,
            ])
            ->assertRedirect(route('sessions.index'));

        $profile = $user->resolveActiveProfile();
        $session = PaddleSession::query()
            ->where('profile_id', $profile->id)
            ->where('title', 'Reykjavik Kayak')
            ->firstOrFail();

        $this->assertSame(7.0, (float) $session->distance_km);
        $this->assertSame(101, (int) $session->duration_minutes);
        $this->assertSame(83, (int) $session->moving_minutes);
        $this->assertSame(14.5, (float) $session->air_temp_c);
    }

    public function test_garmin_import_reuses_existing_session_when_external_ref_shifts(): void
    {
        $user = User::factory()->create();

        $firstCsv = UploadedFile::fake()->createWithContent('activities.csv', implode("\n", [
            'Date,Title,Activity Type,Distance,Moving Time,Elapsed Time,Min Temp,Max Temp',
            '2026-04-01 18:15:00,Reykjavik Kayaking,Kayaking,8.4,01:20:00,01:25:00,5,8',
        ]));

        $this->actingAs($user)
            ->post(route('imports.garmin.store'), [
                'csv_file' => $firstCsv,
            ])
            ->assertRedirect(route('sessions.index'));

        $secondCsv = UploadedFile::fake()->createWithContent('activities-overlap.csv', implode("\n", [
            'Date,Title,Activity Type,Distance,Moving Time,Elapsed Time,Min Temp,Max Temp',
            '2026-04-01 18:15:30,Reykjavik Kayaking duplicate,Kayaking,8.4,01:20:00,01:25:00,6,9',
        ]));

        $this->actingAs($user)
            ->post(route('imports.garmin.store'), [
                'csv_file' => $secondCsv,
            ])
            ->assertRedirect(route('sessions.index'));

        $profile = $user->resolveActiveProfile();
        $session = PaddleSession::query()
            ->where('profile_id', $profile->id)
            ->sole();

        $this->assertSame('Reykjavik Kayaking duplicate', $session->title);
        $this->assertSame('garmin:2026-04-01 18:15:30', $session->external_ref);
        $this->assertSame(8.4, (float) $session->distance_km);
    }

    public function test_single_activity_garmin_csv_can_import_when_uploaded_with_gpx(): void
    {
        $user = User::factory()->create();

        $csv = UploadedFile::fake()->createWithContent('activity_22629961007.csv', implode("\n", [
            '"Laps","Time","Cumulative Time","Distance","Avg Pace","Avg HR","Max HR","Total Ascent","Total Descent","Calories","Avg Temperature","Best Pace","Moving Time","Avg Moving Pace"',
            '"1","1:56:53","1:56:53","11.00","10:38","93","106","10","6","644","23.0","6:45","1:45:41","9:37"',
            '"Summary","1:56:53","1:56:53","11.00","10:38","93","106","10","6","644","23.0","6:45","1:45:41","9:37"',
        ]));

        $gpx = UploadedFile::fake()->createWithContent('activity_22629961007.gpx', <<<'GPX'
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="test">
  <metadata>
    <time>2026-04-22T09:15:00Z</time>
  </metadata>
  <trk>
    <name>Imported activity</name>
    <trkseg>
      <trkpt lat="53.308900" lon="-4.633000"><time>2026-04-22T09:15:00Z</time></trkpt>
      <trkpt lat="53.318900" lon="-4.623000"><time>2026-04-22T09:45:00Z</time></trkpt>
      <trkpt lat="53.328900" lon="-4.613000"><time>2026-04-22T10:15:00Z</time></trkpt>
    </trkseg>
  </trk>
</gpx>
GPX);

        $this->actingAs($user)
            ->post(route('imports.garmin.store'), [
                'csv_file' => $csv,
                'gpx_files' => [$gpx],
            ])
            ->assertRedirect(route('sessions.index'));

        $profile = $user->resolveActiveProfile();
        $session = PaddleSession::query()
            ->where('profile_id', $profile->id)
            ->where('title', 'Imported kayaking session')
            ->firstOrFail();

        $this->assertSame(11.0, (float) $session->distance_km);
        $this->assertSame(117, (int) $session->duration_minutes);
        $this->assertSame(106, (int) $session->moving_minutes);
        $this->assertSame(23.0, (float) $session->air_temp_c);
        $this->assertNotNull($session->gpx_path);
        $this->assertNotEmpty($session->route_profile);
        $this->assertSame('activity_22629961007.gpx', $session->garmin_gpx_name);
    }

    public function test_single_activity_garmin_csv_requires_exactly_one_track_file(): void
    {
        $user = User::factory()->create();

        $csv = UploadedFile::fake()->createWithContent('activity_22629961007.csv', implode("\n", [
            '"Laps","Time","Cumulative Time","Distance","Avg Pace","Avg HR","Max HR","Total Ascent","Total Descent","Calories","Avg Temperature","Best Pace","Moving Time","Avg Moving Pace"',
            '"Summary","1:56:53","1:56:53","11.00","10:38","93","106","10","6","644","23.0","6:45","1:45:41","9:37"',
        ]));

        $this->actingAs($user)
            ->from(route('imports.garmin.create'))
            ->post(route('imports.garmin.store'), [
                'csv_file' => $csv,
            ])
            ->assertRedirect(route('imports.garmin.create'))
            ->assertSessionHasErrors('csv_file');
    }

    public function test_gpx_files_can_be_attached_to_existing_garmin_sessions_without_csv(): void
    {
        $user = User::factory()->create();

        $csv = UploadedFile::fake()->createWithContent('activities.csv', implode("\n", [
            'Date,Title,Activity Type,Distance,Moving Time,Elapsed Time,Min Temp,Max Temp',
            '2026-04-01 18:15:00,Reykjavik Kayaking,Kayaking,8.4,01:20:00,01:25:00,5,8',
        ]));

        $this->actingAs($user)
            ->post(route('imports.garmin.store'), [
                'csv_file' => $csv,
            ])
            ->assertRedirect(route('sessions.index'));

        $gpx = UploadedFile::fake()->createWithContent('reykjavik-route.gpx', <<<'GPX'
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="test">
  <metadata>
    <time>2026-04-01T18:15:00Z</time>
  </metadata>
  <trk>
    <name>Reykjavik Kayaking</name>
    <trkseg>
      <trkpt lat="64.146600" lon="-21.942600"><time>2026-04-01T18:15:00Z</time></trkpt>
      <trkpt lat="64.147600" lon="-21.932600"><time>2026-04-01T18:25:00Z</time></trkpt>
      <trkpt lat="64.148600" lon="-21.922600"><time>2026-04-01T18:35:00Z</time></trkpt>
    </trkseg>
  </trk>
</gpx>
GPX);

        $this->actingAs($user)
            ->post(route('imports.garmin.store'), [
                'gpx_files' => [$gpx],
            ])
            ->assertRedirect(route('sessions.index'));

        $profile = $user->resolveActiveProfile();
        $session = PaddleSession::query()
            ->where('profile_id', $profile->id)
            ->where('title', 'Reykjavik Kayaking')
            ->firstOrFail();

        $this->assertNotNull($session->gpx_path);
        $this->assertSame('reykjavik-route.gpx', $session->garmin_gpx_name);
        $this->assertNotEmpty($session->route_profile);
        $this->assertSame(1, PaddleSession::query()->where('profile_id', $profile->id)->count());
    }

    public function test_garmin_import_can_match_fit_files(): void
    {
        $user = User::factory()->create();
        $fitFixture = file_get_contents(base_path('vendor/adriangibbons/php-fit-file-analysis/demo/fit_files/road-cycling.fit'));
        $this->assertNotFalse($fitFixture);

        $csv = UploadedFile::fake()->createWithContent('activities.csv', implode("\n", [
            'Date,Title,Activity Type,Distance,Moving Time,Elapsed Time,Min Temp,Max Temp',
            '2015-07-16 05:52:31,Road cycling import,Kayaking,36.9,01:54:00,01:55:00,16,22',
        ]));

        $fit = UploadedFile::fake()->createWithContent('road-cycling.fit', $fitFixture);

        $this->actingAs($user)
            ->post(route('imports.garmin.store'), [
                'csv_file' => $csv,
                'fit_files' => [$fit],
            ])
            ->assertRedirect(route('sessions.index'));

        $profile = $user->resolveActiveProfile();
        $session = PaddleSession::query()
            ->where('profile_id', $profile->id)
            ->where('title', 'Road cycling import')
            ->firstOrFail();

        $this->assertNotNull($session->fit_path);
        $this->assertNotEmpty($session->route_profile);
        $this->assertNotNull($session->launch_lat);
        $this->assertNotNull($session->launch_lng);
    }

    public function test_garmin_import_can_autofill_weather_and_derive_beaufort(): void
    {
        config()->set('kayak.weather.providers.stormglass.api_key', 'test-key');

        Http::fake([
            'api.stormglass.io/v2/weather/point*' => Http::response([
                'hours' => [[
                    'time' => '2015-07-16T05:00:00+00:00',
                    'windSpeed' => ['sg' => 8.2],
                    'gust' => ['sg' => 11.6],
                    'windDirection' => ['sg' => 195],
                    'precipitation' => ['sg' => 0.2],
                    'airTemperature' => ['sg' => 12.4],
                    'waterTemperature' => ['sg' => 9.1],
                    'visibility' => ['sg' => 8000],
                    'currentSpeed' => ['sg' => 0.3],
                    'currentDirection' => ['sg' => 140],
                    'waveHeight' => ['sg' => 0.5],
                    'swellHeight' => ['sg' => 0.7],
                    'swellPeriod' => ['sg' => 4.8],
                    'swellDirection' => ['sg' => 220],
                ]],
            ]),
            'api.stormglass.io/v2/tide/extremes/point*' => Http::response([
                'data' => [
                    ['time' => '2015-07-16T04:40:00+00:00', 'type' => 'high', 'height' => 2.8],
                    ['time' => '2015-07-16T10:50:00+00:00', 'type' => 'low', 'height' => 0.6],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $fitFixture = file_get_contents(base_path('vendor/adriangibbons/php-fit-file-analysis/demo/fit_files/road-cycling.fit'));
        $this->assertNotFalse($fitFixture);

        $csv = UploadedFile::fake()->createWithContent('activities.csv', implode("\n", [
            'Date,Title,Activity Type,Distance,Moving Time,Elapsed Time,Min Temp,Max Temp',
            '2015-07-16 05:52:31,Road cycling import,Kayaking,36.9,01:54:00,01:55:00,16,22',
        ]));

        $fit = UploadedFile::fake()->createWithContent('road-cycling.fit', $fitFixture);

        $this->actingAs($user)
            ->post(route('imports.garmin.store'), [
                'csv_file' => $csv,
                'fit_files' => [$fit],
                'autofill_weather' => true,
            ])
            ->assertRedirect(route('sessions.index'));

        $profile = $user->resolveActiveProfile();
        $session = PaddleSession::query()
            ->where('profile_id', $profile->id)
            ->where('title', 'Road cycling import')
            ->firstOrFail();

        $this->assertSame(5, $session->wind_beaufort);
        $this->assertSame(8.2, (float) $session->wind_avg_ms);
        $this->assertSame('ebbing', $session->tide_state);
        $this->assertSame('low', $session->rain_severity);
        $this->assertSame('moderate', $session->wind_severity);
        $this->assertSame('moderate', $session->temperature_severity);
        $this->assertSame('moderate', $session->forecast_severity);
        $this->assertNotNull($session->weather_summary);
    }
}
