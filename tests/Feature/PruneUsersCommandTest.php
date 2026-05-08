<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneUsersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_users_command_dry_runs_without_deleting_accounts(): void
    {
        User::factory()->create(['email' => 'francescolivigni@gmail.com']);
        User::factory()->create(['email' => 'friend@example.com']);

        $this->artisan('kayak:prune-users', [
            '--keep' => 'francescolivigni@gmail.com',
        ])
            ->expectsOutputToContain('Dry run only')
            ->assertSuccessful();

        $this->assertSame(2, User::query()->count());
    }

    public function test_prune_users_command_deletes_everyone_except_the_keeper(): void
    {
        Storage::fake('public');
        config()->set('kayak.media_disk', 'public');

        $keeper = User::factory()->create(['email' => 'francescolivigni@gmail.com']);
        $deletedUser = User::factory()->create(['email' => 'friend@example.com']);
        $profile = $deletedUser->resolveActiveProfile();
        $profile->sessions()->create([
            'recorded_by_user_id' => $deletedUser->id,
            'session_date' => '2026-04-06',
            'title' => 'Friend paddle',
            'route_category' => 'journey',
            'distance_km' => 4.2,
            'gpx_path' => 'gpx/friend.gpx',
        ]);
        Storage::disk('public')->put('gpx/friend.gpx', '<gpx />');

        $this->artisan('kayak:prune-users', [
            '--keep' => 'francescolivigni@gmail.com',
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas(User::class, [
            'id' => $keeper->id,
            'email' => 'francescolivigni@gmail.com',
        ]);
        $this->assertDatabaseMissing(User::class, [
            'id' => $deletedUser->id,
        ]);
        $this->assertDatabaseMissing('profiles', [
            'id' => $profile->id,
        ]);
        Storage::disk('public')->assertMissing('gpx/friend.gpx');
    }
}
