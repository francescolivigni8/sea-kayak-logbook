<?php

namespace Tests\Feature;

use App\Models\PaddleSession;
use App\Models\User;
use App\Support\StormglassWeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StormglassWeatherServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_quota_failures_are_cached_to_avoid_repeated_provider_requests(): void
    {
        config()->set('kayak.weather.providers.stormglass.api_key', 'test-key');
        config()->set('kayak.weather.providers.stormglass.source', null);
        Cache::flush();

        Http::fake([
            'api.stormglass.io/v2/weather/point*' => Http::response([
                'errors' => ['key' => 'API quota exceeded'],
                'meta' => ['dailyQuota' => 10, 'requestCount' => 26],
            ], 402),
        ]);

        $user = User::factory()->create();
        $profile = $user->resolveActiveProfile();
        $sessions = collect([0, 1])->map(fn (int $index) => PaddleSession::create([
            'profile_id' => $profile->id,
            'recorded_by_user_id' => $user->id,
            'session_date' => now()->subDays($index)->toDateString(),
            'start_at' => now()->subDays($index),
            'timezone' => 'Atlantic/Reykjavik',
            'title' => 'Quota test '.$index,
            'launch_lat' => 64.1466 + $index,
            'launch_lng' => -21.9426,
            'distance_km' => 1,
            'duration_minutes' => 60,
        ]));

        $summary = app(StormglassWeatherService::class)->enrichSessions($sessions);

        $this->assertSame(2, $summary['failed']);
        Http::assertSentCount(1);
    }
}
