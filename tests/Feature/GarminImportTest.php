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

        $this->assertDatabaseHas(PaddleSession::class, [
            'profile_id' => $profile->id,
            'title' => 'Reykjavik Kayaking',
            'distance_km' => 8.4,
        ]);
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
