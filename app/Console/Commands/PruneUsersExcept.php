<?php

namespace App\Console\Commands;

use App\Models\PaddleSession;
use App\Models\User;
use App\Support\SessionMediaService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class PruneUsersExcept extends Command
{
    protected $signature = 'kayak:prune-users
        {--keep= : Email address of the only user account to keep}
        {--force : Delete the matching users. Without this option the command only previews the change.}
    ';

    protected $description = 'Delete every user account except the specified owner account';

    public function handle(SessionMediaService $media): int
    {
        $keepEmail = strtolower(trim((string) $this->option('keep')));

        if ($keepEmail === '') {
            $this->error('Add --keep=owner@example.com so the protected account is explicit.');

            return self::FAILURE;
        }

        $keeper = User::query()
            ->whereRaw('LOWER(email) = ?', [$keepEmail])
            ->first();

        if (! $keeper) {
            $this->error("No user exists with the email {$keepEmail}. Nothing was deleted.");

            return self::FAILURE;
        }

        /** @var Collection<int, User> $users */
        $users = User::query()
            ->whereRaw('LOWER(email) <> ?', [$keepEmail])
            ->orderBy('id')
            ->get();

        if ($users->isEmpty()) {
            $this->info("Only {$keeper->email} exists. Nothing to delete.");

            return self::SUCCESS;
        }

        $this->warn("Protected account: {$keeper->email} (id {$keeper->id})");
        $this->table(
            ['id', 'email', 'name'],
            $users->map(fn (User $user): array => [
                $user->id,
                $user->email,
                $user->name,
            ])->all(),
        );

        if (! $this->option('force')) {
            $this->warn("Dry run only. Re-run with --force to delete {$users->count()} user(s).");

            return self::SUCCESS;
        }

        $paths = $this->mediaPathsForUsers($users);
        $emails = $users->pluck('email')->all();
        $ids = $users->pluck('id')->all();

        DB::transaction(function () use ($emails, $ids, $users): void {
            DB::table('password_reset_tokens')->whereIn('email', $emails)->delete();
            DB::table('sessions')->whereIn('user_id', $ids)->delete();

            $users->each(fn (User $user) => $user->delete());
        });

        $this->deleteMediaPaths($paths, $media);
        $this->info("Deleted {$users->count()} user(s). Kept {$keeper->email}.");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array<int, string>
     */
    private function mediaPathsForUsers(Collection $users): array
    {
        return $users
            ->flatMap(fn (User $user) => $user->ownedProfiles()
                ->with(['sessions:id,profile_id,gpx_path,fit_path,session_photo_path'])
                ->get()
                ->flatMap(fn ($profile) => $profile->sessions))
            ->flatMap(fn (PaddleSession $session): array => [
                $session->gpx_path,
                $session->fit_path,
                $session->session_photo_path,
            ])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function deleteMediaPaths(array $paths, SessionMediaService $media): void
    {
        foreach ($paths as $path) {
            try {
                $media->delete($path);
            } catch (Throwable $exception) {
                report($exception);
                $this->warn("Deleted database rows, but could not remove media file {$path}.");
            }
        }
    }
}
