<?php

namespace App\Console\Commands;

use App\Models\PaddleSession;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairGarminDecimalCommaImports extends Command
{
    protected $signature = 'kayak:repair-garmin-decimal-commas
        {email? : User email to repair}
        {--all : Scan every user}
        {--force : Apply the repairs instead of showing a dry run}
    ';

    protected $description = 'Repair Garmin CSV imports where decimal comma values were imported as much larger numbers';

    public function handle(): int
    {
        $users = $this->usersToRepair();

        if ($users->isEmpty()) {
            return self::FAILURE;
        }

        $rows = [];
        $repairCount = 0;
        $apply = (bool) $this->option('force');

        foreach ($users as $user) {
            foreach ($user->ownedProfiles as $profile) {
                foreach ($this->candidateSessions($profile) as $session) {
                    $updates = $this->repairPayload($session);

                    if ($updates === []) {
                        continue;
                    }

                    $rows[] = [
                        $user->email,
                        $session->id,
                        $session->session_date?->toDateString(),
                        $session->title,
                        $this->formatChange($session->distance_km, $updates['distance_km'] ?? null),
                        $this->formatChange($session->air_temp_c, $updates['air_temp_c'] ?? null),
                        $this->formatChange($session->route_category, $updates['route_category'] ?? null),
                    ];

                    if ($apply) {
                        $session->forceFill($updates)->save();
                    }

                    $repairCount++;
                }
            }
        }

        if ($rows !== []) {
            $this->table(
                ['User', 'Session', 'Date', 'Title', 'Distance km', 'Air temp C', 'Category'],
                $rows,
            );
        }

        if (! $apply) {
            $this->warn("Dry run only. Re-run with --force to repair {$repairCount} sessions.");

            return self::SUCCESS;
        }

        $this->info("Repaired {$repairCount} Garmin sessions.");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, User>
     */
    private function usersToRepair(): Collection
    {
        $email = trim(strtolower((string) $this->argument('email')));

        if ($email === '' && ! $this->option('all')) {
            $this->error('Provide a user email, or use --all for a global dry run.');

            return collect();
        }

        $query = User::query()->with('ownedProfiles');

        if (! $this->option('all')) {
            $query->where('email', $email);
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->error($email !== '' ? "User not found: {$email}" : 'No users found.');
        }

        return $users;
    }

    /**
     * @return Collection<int, PaddleSession>
     */
    private function candidateSessions(Profile $profile): Collection
    {
        return $profile->sessions()
            ->where('external_ref', 'like', 'garmin:%')
            ->where(function ($query) {
                $query->where('distance_km', '>=', 100)
                    ->orWhere('air_temp_c', '>=', 70);
            })
            ->orderBy('session_date')
            ->get();
    }

    private function repairPayload(PaddleSession $session): array
    {
        $updates = [];
        $distanceKm = (float) $session->distance_km;
        $correctedDistanceKm = $distanceKm;

        if ($distanceKm >= 100) {
            $correctedDistanceKm = round($distanceKm / 100, 2);
            $updates['distance_km'] = $correctedDistanceKm;
            $updates['route_category'] = $this->inferCategory(
                $correctedDistanceKm,
                (int) ($session->moving_minutes ?: $session->duration_minutes),
            );
        }

        if ($session->air_temp_c !== null && (float) $session->air_temp_c >= 70) {
            $updates['air_temp_c'] = round(((float) $session->air_temp_c) / 10, 1);
        }

        if (array_key_exists('distance_km', $updates)) {
            $tags = $this->repairRouteTags($session->route_tags ?? [], $correctedDistanceKm);

            if ($tags !== ($session->route_tags ?? [])) {
                $updates['route_tags'] = $tags;
            }
        }

        return $updates;
    }

    private function repairRouteTags(array $tags, float $distanceKm): array
    {
        if (! in_array('garmin-import', $tags, true)) {
            return $tags;
        }

        $sizeTag = $distanceKm >= 15
            ? 'longer-day'
            : ($distanceKm >= 8 ? 'mid-distance' : 'short-day');

        return collect($tags)
            ->reject(fn (string $tag) => in_array($tag, ['short-day', 'mid-distance', 'longer-day'], true))
            ->push($sizeTag)
            ->unique()
            ->values()
            ->all();
    }

    private function inferCategory(float $distanceKm, int $movingMinutes): string
    {
        if ($distanceKm <= 0 || $movingMinutes <= 0) {
            return 'training';
        }

        if ($distanceKm >= 15 || $movingMinutes >= 160) {
            return 'journey';
        }

        if ($distanceKm <= 6) {
            return 'benchmark';
        }

        return 'training';
    }

    private function formatChange(mixed $before, mixed $after): string
    {
        if ($after === null || $before === $after) {
            return (string) $before;
        }

        return (string) $before.' -> '.$after;
    }
}
