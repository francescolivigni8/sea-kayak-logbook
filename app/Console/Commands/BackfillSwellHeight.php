<?php

namespace App\Console\Commands;

use App\Models\PaddleSession;
use App\Models\User;
use App\Support\OpenMeteoMarineWeatherService;
use App\Support\StormglassWeatherService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class BackfillSwellHeight extends Command
{
    protected $signature = 'kayak:backfill-swell-height
        {--email= : Limit the backfill to the active profile of this user}
        {--profile= : Limit the backfill to one profile id}
        {--limit= : Stop after this many candidate sessions}
        {--dry-run : Preview how many sessions would be updated without saving}
    ';

    protected $description = 'Backfill missing swell height data on logged paddle sessions';

    public function handle(
        StormglassWeatherService $stormglass,
        OpenMeteoMarineWeatherService $openMeteo,
    ): int {
        $query = PaddleSession::query()
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('swell_height_m')
                    ->orWhere('swell_height_m', 0);
            })
            ->where(function (Builder $query): void {
                $query
                    ->whereNotNull('launch_lat')
                    ->whereNotNull('launch_lng')
                    ->orWhere(function (Builder $landing): void {
                        $landing
                            ->whereNotNull('landing_lat')
                            ->whereNotNull('landing_lng');
                    });
            })
            ->orderBy('session_date')
            ->orderBy('id');

        if ($email = $this->option('email')) {
            $user = User::query()->where('email', (string) $email)->first();

            if (! $user) {
                $this->error("No user found for {$email}.");

                return self::FAILURE;
            }

            $query->where('profile_id', $user->resolveActiveProfile()->id);
        }

        if ($profileId = $this->option('profile')) {
            $query->where('profile_id', (int) $profileId);
        }

        if ($limit = $this->option('limit')) {
            $query->limit(max((int) $limit, 1));
        }

        $sessions = $query->get();

        if ($sessions->isEmpty()) {
            $this->info('No sessions with missing swell height were found.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d candidate sessions.', $sessions->count()));

        if (! $stormglass->isConfigured() && ! $openMeteo->isConfigured()) {
            $this->error('No weather provider is configured for swell backfill.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $stormglassHits = 0;
        $openMeteoHits = 0;

        $bar = $this->output->createProgressBar($sessions->count());
        $bar->start();

        foreach ($sessions as $session) {
            $result = $this->backfillSession($session, $stormglass, $openMeteo, $dryRun);

            match ($result['status']) {
                'updated' => $updated++,
                'failed' => $failed++,
                default => $skipped++,
            };

            if (($result['provider'] ?? null) === 'stormglass') {
                $stormglassHits++;
            }

            if (($result['provider'] ?? null) === 'open_meteo') {
                $openMeteoHits++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $verb = $dryRun ? 'Would update' : 'Updated';
        $this->info(sprintf('%s %d sessions.', $verb, $updated));
        $this->line("Skipped: {$skipped}");
        $this->line("Failed: {$failed}");
        $this->line("Stormglass fills: {$stormglassHits}");
        $this->line("Open-Meteo fills: {$openMeteoHits}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{status: 'updated'|'skipped'|'failed', provider?: string|null}
     */
    private function backfillSession(
        PaddleSession $session,
        StormglassWeatherService $stormglass,
        OpenMeteoMarineWeatherService $openMeteo,
        bool $dryRun,
    ): array {
        $candidate = $session->replicate();
        $candidate->setRelation('profile', $session->profile);
        $providerUsed = null;

        if ($stormglass->isConfigured()) {
            $stormglassResult = $stormglass->previewSession($candidate);

            if (($stormglassResult['status'] ?? null) === 'filled') {
                $providerUsed = 'stormglass';
                $this->applyMarineFields($session, (array) ($stormglassResult['fields'] ?? []));
            } elseif (($stormglassResult['status'] ?? null) === 'failed') {
                return ['status' => 'failed', 'provider' => 'stormglass'];
            }
        }

        if ($this->needsSwellBackfill($session) && $openMeteo->isConfigured()) {
            $openMeteoResult = $openMeteo->previewForecastBoard($candidate);

            if (($openMeteoResult['status'] ?? null) === 'filled') {
                $providerUsed = 'open_meteo';
                $this->applyMarineFields($session, (array) ($openMeteoResult['fields'] ?? []));
            } elseif (($openMeteoResult['status'] ?? null) === 'failed') {
                return ['status' => 'failed', 'provider' => 'open_meteo'];
            }
        }

        if ($this->needsSwellBackfill($session)) {
            return ['status' => 'skipped', 'provider' => $providerUsed];
        }

        if (! $dryRun) {
            $session->conditions_logged = true;
            $session->save();
        }

        return ['status' => 'updated', 'provider' => $providerUsed];
    }

    private function applyMarineFields(PaddleSession $session, array $fields): void
    {
        foreach ([
            'wave_height_m',
            'swell_height_m',
            'swell_period_s',
            'swell_direction_deg',
        ] as $field) {
            $current = $session->{$field};
            $incoming = $fields[$field] ?? null;

            if (($current === null || (float) $current === 0.0) && $incoming !== null && (float) $incoming !== 0.0) {
                $session->{$field} = $incoming;
            }
        }

        if (! filled($session->weather_summary) && filled($fields['weather_summary'] ?? null)) {
            $session->weather_summary = $fields['weather_summary'];
        }
    }

    private function needsSwellBackfill(PaddleSession $session): bool
    {
        return $session->swell_height_m === null || (float) $session->swell_height_m === 0.0;
    }
}
