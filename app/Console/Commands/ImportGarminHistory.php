<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\GarminImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ImportGarminHistory extends Command
{
    protected $signature = 'kayak:import-garmin
        {email : The user email that owns the target profile}
        {csv : Absolute path to the Garmin CSV export}
        {--gpx-dir= : Optional directory containing GPX exports}
        {--name= : Name to use if the user does not exist yet}
        {--password= : Password to set if the user does not exist yet}
    ';

    protected $description = 'Import Garmin kayaking history into the Laravel sea kayak logbook';

    public function handle(GarminImportService $importer): int
    {
        $email = (string) $this->argument('email');
        $csvPath = (string) $this->argument('csv');
        $gpxDir = $this->option('gpx-dir') ? (string) $this->option('gpx-dir') : null;

        if (! is_file($csvPath)) {
            $this->error("CSV file not found: {$csvPath}");

            return self::FAILURE;
        }

        if ($gpxDir && ! is_dir($gpxDir)) {
            $this->error("GPX directory not found: {$gpxDir}");

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $name = (string) ($this->option('name') ?: Str::headline(Str::before($email, '@')));
            $password = (string) ($this->option('password') ?: 'kayaklogbook');

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
            ]);

            $user->forceFill([
                'email_verified_at' => Carbon::now(),
            ])->save();

            $this->info("Created local user {$email} with password: {$password}");
        }

        $profile = $user->resolveActiveProfile();
        $summary = $importer->import($profile, $csvPath, $gpxDir);

        $this->newLine();
        $this->info("Imported {$summary['imported']} sessions into {$summary['profile']}.");
        $this->line("Distance imported: {$summary['distanceKm']} km");
        $this->line("Matched GPX files: {$summary['gpxMatched']}");

        if (! empty($summary['gpxUnmatched'])) {
            $this->warn('Unmatched GPX files: '.implode(', ', $summary['gpxUnmatched']));
        }

        return self::SUCCESS;
    }
}
