<?php

namespace App\Console\Commands;

use App\Models\PaddleSession;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class PruneDuplicatePaddleSessions extends Command
{
    protected $signature = 'kayak:prune-duplicate-sessions
        {--email= : Limit cleanup to this user active profile}
        {--profile= : Limit cleanup to a profile id}
        {--force : Delete duplicates instead of showing a dry run}
    ';

    protected $description = 'Preview or delete duplicate paddle sessions while keeping the best route-rich copy';

    public function handle(): int
    {
        $profile = $this->resolveProfile();

        if (! $profile) {
            return self::FAILURE;
        }

        $sessions = $profile->sessions()
            ->orderBy('session_date')
            ->orderBy('start_at')
            ->orderBy('created_at')
            ->get();

        $duplicates = $this->duplicateRows($sessions);

        if ($duplicates->isEmpty()) {
            $this->info("No duplicate sessions found for profile {$profile->id} ({$profile->name}).");

            return self::SUCCESS;
        }

        $this->table(
            ['Action', 'ID', 'Date', 'Start', 'Distance', 'Duration', 'Title', 'GPX', 'Route pts', 'Created'],
            $duplicates->map(fn (array $row): array => [
                $row['delete'] ? 'delete' : 'keep',
                $row['session']->id,
                $row['session']->session_date?->toDateString(),
                $row['session']->start_at?->format('H:i'),
                number_format((float) $row['session']->distance_km, 2).' km',
                $row['session']->duration_minutes.'m',
                $row['session']->title,
                filled($row['session']->garmin_gpx_name) ? $row['session']->garmin_gpx_name : '',
                filled($row['session']->route_points) ? 'yes' : '',
                $row['session']->created_at?->toDateTimeString(),
            ])->all(),
        );

        $deleteIds = $duplicates
            ->filter(fn (array $row): bool => $row['delete'])
            ->map(fn (array $row): int => $row['session']->id)
            ->values();

        if ($deleteIds->isEmpty()) {
            $this->info('Duplicate groups were found, but no delete candidates were selected.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->warn('Dry run only. Re-run with --force to delete '.count($deleteIds).' duplicate sessions.');

            return self::SUCCESS;
        }

        $deleted = PaddleSession::query()
            ->whereIn('id', $deleteIds)
            ->delete();

        $this->info("Deleted {$deleted} duplicate sessions.");

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

    /**
     * @param  Collection<int, PaddleSession>  $sessions
     * @return Collection<int, array{session: PaddleSession, delete: bool}>
     */
    private function duplicateRows(Collection $sessions): Collection
    {
        return $sessions
            ->groupBy(fn (PaddleSession $session): string => $this->fingerprint($session))
            ->filter(fn (Collection $group): bool => $group->count() > 1)
            ->flatMap(function (Collection $group): array {
                $ranked = $group
                    ->sortByDesc(fn (PaddleSession $session): int => $this->qualityScore($session))
                    ->values();

                return $ranked
                    ->map(fn (PaddleSession $session, int $index): array => [
                        'session' => $session,
                        'delete' => $index > 0,
                    ])
                    ->all();
            })
            ->values();
    }

    private function fingerprint(PaddleSession $session): string
    {
        $start = $session->start_at?->copy()->second(0)->format('Y-m-d H:i') ?? 'no-start';
        $distance = number_format(round((float) $session->distance_km, 1), 1, '.', '');
        $duration = (string) ((int) round(((int) $session->duration_minutes) / 2) * 2);

        return implode('|', [
            $session->profile_id,
            $session->session_date?->toDateString(),
            $start,
            $distance,
            $duration,
        ]);
    }

    private function qualityScore(PaddleSession $session): int
    {
        return 0
            + (filled($session->route_points) ? 100 : 0)
            + (filled($session->garmin_gpx_name) ? 50 : 0)
            + (filled($session->route_profile) && $session->route_profile !== [] ? 25 : 0)
            + (filled($session->notes_private) ? 5 : 0)
            + (filled($session->notes_public) ? 5 : 0)
            + (filled($session->session_photo_path) ? 5 : 0)
            + (filled($session->external_ref) ? 1 : 0);
    }
}
