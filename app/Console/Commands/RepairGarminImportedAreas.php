<?php

namespace App\Console\Commands;

use App\Models\PaddleSession;
use App\Models\User;
use App\Support\GarminImportService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class RepairGarminImportedAreas extends Command
{
    protected $signature = 'kayak:repair-garmin-areas
        {--email= : Limit the repair to the active profile of this user}
        {--profile= : Limit the repair to one profile id}
        {--all : Scan every user}
        {--force : Apply the repairs instead of showing a dry run}
    ';

    protected $description = 'Repair legacy Garmin-imported session areas that were defaulted to Faxafloi/Reykjavik';

    public function handle(GarminImportService $importer): int
    {
        $query = PaddleSession::query()
            ->where('external_ref', 'like', 'garmin:%')
            ->orderBy('session_date')
            ->orderBy('id');

        if (! $this->applyScope($query)) {
            return self::FAILURE;
        }

        $sessions = $query->get();

        if ($sessions->isEmpty()) {
            $this->info('No Garmin-imported sessions were found for that scope.');

            return self::SUCCESS;
        }

        $rows = [];
        $repairCount = 0;
        $apply = (bool) $this->option('force');

        foreach ($sessions as $session) {
            $updates = $importer->legacyLocationRepairPayload($session);

            if ($updates === []) {
                continue;
            }

            $rows[] = [
                $session->id,
                $session->session_date?->toDateString(),
                $session->title,
                $this->formatChange($session->area_name, $updates['area_name'] ?? null),
                $this->formatChange($session->launch_name, $updates['launch_name'] ?? null),
                $this->formatChange($session->landing_name, $updates['landing_name'] ?? null),
            ];

            if ($apply) {
                $session->forceFill($updates)->save();
            }

            $repairCount++;
        }

        if ($rows !== []) {
            $this->table(
                ['Session', 'Date', 'Title', 'Area', 'Launch', 'Landing'],
                $rows,
            );
        }

        if (! $apply) {
            $this->warn("Dry run only. Re-run with --force to repair {$repairCount} Garmin sessions.");

            return self::SUCCESS;
        }

        $this->info("Repaired {$repairCount} Garmin sessions.");

        return self::SUCCESS;
    }

    private function applyScope(Builder $query): bool
    {
        $email = trim(strtolower((string) $this->option('email')));
        $profileId = (int) $this->option('profile');
        $all = (bool) $this->option('all');

        if ($email === '' && $profileId <= 0 && ! $all) {
            $this->error('Use --email=..., --profile=..., or --all to choose which Garmin imports to repair.');

            return false;
        }

        if ($email !== '') {
            $user = User::query()->where('email', $email)->first();

            if (! $user) {
                $this->error("No user found for {$email}.");

                return false;
            }

            $query->where('profile_id', $user->resolveActiveProfile()->id);
        }

        if ($profileId > 0) {
            $query->where('profile_id', $profileId);
        }

        return true;
    }

    private function formatChange(mixed $before, mixed $after): string
    {
        if ($after === null || $before === $after) {
            return (string) $before;
        }

        return (string) $before.' -> '.$after;
    }
}
