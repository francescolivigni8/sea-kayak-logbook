<?php

namespace App\Console\Commands;

use App\Models\PaddleSession;
use App\Models\Profile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class DeleteLatestCsvOnlyUpload extends Command
{
    protected $signature = 'kayak:delete-latest-csv-upload
        {--email= : Limit cleanup to this user active profile}
        {--profile= : Limit cleanup to a profile id}
        {--minutes=20 : Delete Garmin CSV-only rows created within this many minutes of the latest imported row}
        {--force : Delete rows instead of showing a dry run}
    ';

    protected $description = 'Preview or delete the latest Garmin CSV-only upload batch for one profile';

    public function handle(): int
    {
        $profile = $this->resolveProfile();

        if (! $profile) {
            return self::FAILURE;
        }

        $latestCreatedAt = $this->csvOnlyQuery($profile)
            ->max('created_at');

        if (! $latestCreatedAt) {
            $this->info("No Garmin CSV-only imports found for profile {$profile->id} ({$profile->name}).");

            return self::SUCCESS;
        }

        $minutes = max(1, (int) $this->option('minutes'));
        $latest = CarbonImmutable::parse($latestCreatedAt);
        $since = $latest->subMinutes($minutes);

        $sessions = $this->csvOnlyQuery($profile)
            ->where('created_at', '>=', $since)
            ->orderBy('created_at')
            ->orderBy('session_date')
            ->get();

        if ($sessions->isEmpty()) {
            $this->info("No Garmin CSV-only imports found since {$since->toDateTimeString()}.");

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Date', 'Start', 'Distance', 'Duration', 'Title', 'Created'],
            $sessions->map(fn (PaddleSession $session): array => [
                $session->id,
                $session->session_date?->toDateString(),
                $session->start_at?->format('H:i'),
                number_format((float) $session->distance_km, 2).' km',
                $session->duration_minutes.'m',
                $session->title,
                $session->created_at?->toDateTimeString(),
            ])->all(),
        );

        if (! $this->option('force')) {
            $this->warn('Dry run only. Re-run with --force to delete '.count($sessions).' latest CSV-only imported sessions.');

            return self::SUCCESS;
        }

        $deleted = PaddleSession::query()
            ->whereIn('id', $sessions->pluck('id'))
            ->delete();

        $remaining = $profile->sessions()->count();

        $this->info("Deleted {$deleted} latest CSV-only imported sessions. Remaining sessions: {$remaining}.");

        return self::SUCCESS;
    }

    private function resolveProfile(): ?Profile
    {
        $profileId = (int) $this->option('profile');
        $email = trim(strtolower((string) $this->option('email')));

        if ($profileId > 0) {
            $profile = Profile::query()->find($profileId);

            if (! $profile) {
                $this->error("Profile {$profileId} was not found.");

                return null;
            }

            return $profile;
        }

        if ($email !== '') {
            $user = User::query()->where('email', $email)->first();

            if (! $user) {
                $this->error("No user found for {$email}.");

                return null;
            }

            return $user->resolveActiveProfile();
        }

        $this->error('Use --email=... or --profile=... to choose which logbook to clean.');

        return null;
    }

    private function csvOnlyQuery(Profile $profile)
    {
        return $profile->sessions()
            ->where('external_ref', 'like', 'garmin:%')
            ->where(function ($query): void {
                $query
                    ->whereNull('route_points')
                    ->orWhere('route_points', '');
            })
            ->where(function ($query): void {
                $query
                    ->whereNull('garmin_gpx_name')
                    ->orWhere('garmin_gpx_name', '');
            })
            ->where(function ($query): void {
                $query
                    ->whereNull('garmin_fit_name')
                    ->orWhere('garmin_fit_name', '');
            });
    }
}
