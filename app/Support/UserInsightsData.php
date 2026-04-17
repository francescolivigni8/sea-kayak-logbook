<?php

namespace App\Support;

use App\Models\Profile;
use App\Models\User;
use Carbon\CarbonInterface;

class UserInsightsData
{
    public function build(): array
    {
        $now = now();
        $users = User::query()
            ->with([
                'ownedProfiles.sessions' => fn ($query) => $query
                    ->select([
                        'id',
                        'profile_id',
                        'session_date',
                        'updated_at',
                        'external_ref',
                        'notes_public',
                    ]),
            ])
            ->latest('created_at')
            ->get();

        $rows = $users->map(fn (User $user) => $this->mapUser($user, $now))->values();
        $sortedUsers = $rows
            ->sortBy([
                ['lastActivitySort', 'asc'],
                ['sessionCount', 'desc'],
                ['joinedSort', 'asc'],
            ])
            ->values();

        $totalUsers = $rows->count();
        $newUsers7d = $rows->filter(fn (array $row) => $row['joinedDaysAgo'] <= 7)->count();
        $newUsers30d = $rows->filter(fn (array $row) => $row['joinedDaysAgo'] <= 30)->count();
        $activeUsers30d = $rows->filter(fn (array $row) => $row['lastActivityDaysAgo'] !== null && $row['lastActivityDaysAgo'] <= 30)->count();
        $setupCompleteUsers = $rows->filter(fn (array $row) => $row['setupComplete'])->count();
        $sessionUsers = $rows->filter(fn (array $row) => $row['sessionCount'] > 0)->count();
        $observationUsers = $rows->filter(fn (array $row) => $row['observationCount'] > 0)->count();
        $importUsers = $rows->filter(fn (array $row) => $row['importedSessionCount'] > 0)->count();
        $noSessionUsers = $rows->filter(fn (array $row) => $row['sessionCount'] === 0)->count();
        $setupIncompleteUsers = $rows->filter(fn (array $row) => ! $row['setupComplete'])->count();
        $dormantUsers = $rows->filter(fn (array $row) => $row['lastActivityDaysAgo'] !== null && $row['lastActivityDaysAgo'] > 60)->count();
        $usersWithoutObservations = $rows->filter(fn (array $row) => $row['sessionCount'] > 0 && $row['observationCount'] === 0)->count();

        $overviewCards = [
            [
                'label' => 'Total users',
                'value' => $totalUsers,
                'detail' => 'All registered accounts in the journal.',
            ],
            [
                'label' => 'New in 30 days',
                'value' => $newUsers30d,
                'detail' => "{$newUsers7d} joined in the last 7 days.",
            ],
            [
                'label' => 'Active in 30 days',
                'value' => $activeUsers30d,
                'detail' => 'Recent activity from account, profile, or session updates.',
            ],
            [
                'label' => 'Setup complete',
                'value' => $setupCompleteUsers,
                'detail' => "{$setupIncompleteUsers} still need to finish their paddler profile.",
            ],
            [
                'label' => 'Logged a session',
                'value' => $sessionUsers,
                'detail' => "{$noSessionUsers} have not logged their first paddle yet.",
            ],
            [
                'label' => 'Added observations',
                'value' => $observationUsers,
                'detail' => "{$usersWithoutObservations} have sessions but no reflections yet.",
            ],
        ];

        $healthCards = [
            [
                'label' => 'Garmin import users',
                'value' => $importUsers,
                'detail' => 'Accounts that have imported at least one Garmin session.',
            ],
            [
                'label' => 'Dormant 60d',
                'value' => $dormantUsers,
                'detail' => 'Users whose last recorded activity is older than 60 days.',
            ],
            [
                'label' => 'No setup yet',
                'value' => $setupIncompleteUsers,
                'detail' => 'Accounts that still need their paddler details completed.',
            ],
            [
                'label' => 'No first session',
                'value' => $noSessionUsers,
                'detail' => 'Accounts that signed up but have not logged a paddle.',
            ],
        ];

        $funnelCounts = [
            ['label' => 'Registered', 'count' => $totalUsers, 'detail' => 'Created an account'],
            ['label' => 'Profile ready', 'count' => $setupCompleteUsers, 'detail' => 'Finished paddler setup'],
            ['label' => 'Logged first paddle', 'count' => $sessionUsers, 'detail' => 'Saved at least one session'],
            ['label' => 'Added a reflection', 'count' => $observationUsers, 'detail' => 'Wrote at least one observation'],
            ['label' => 'Used Garmin import', 'count' => $importUsers, 'detail' => 'Imported data from Garmin'],
        ];

        $funnel = collect($funnelCounts)
            ->map(function (array $stage) use ($totalUsers) {
                $percent = $totalUsers > 0 ? (int) round(($stage['count'] / $totalUsers) * 100) : 0;

                return [
                    ...$stage,
                    'percent' => $percent,
                ];
            })
            ->all();

        $signupTrend = collect(range(5, 0))
            ->map(function (int $monthsAgo) use ($rows, $now) {
                $date = $now->copy()->startOfMonth()->subMonths($monthsAgo - 1);
                $monthKey = $date->format('Y-m');
                $count = $rows->filter(fn (array $row) => str_starts_with($row['joinedMonth'], $monthKey))->count();

                return [
                    'label' => strtoupper($date->format('M')),
                    'month' => $date->format('F Y'),
                    'count' => $count,
                ];
            })
            ->values()
            ->all();

        $sessionTiers = collect([
            ['label' => '0 sessions', 'count' => $rows->filter(fn (array $row) => $row['sessionCount'] === 0)->count()],
            ['label' => '1-4 sessions', 'count' => $rows->filter(fn (array $row) => $row['sessionCount'] >= 1 && $row['sessionCount'] <= 4)->count()],
            ['label' => '5-14 sessions', 'count' => $rows->filter(fn (array $row) => $row['sessionCount'] >= 5 && $row['sessionCount'] <= 14)->count()],
            ['label' => '15+ sessions', 'count' => $rows->filter(fn (array $row) => $row['sessionCount'] >= 15)->count()],
        ])
            ->map(function (array $tier) use ($totalUsers) {
                return [
                    ...$tier,
                    'percent' => $totalUsers > 0 ? (int) round(($tier['count'] / $totalUsers) * 100) : 0,
                ];
            })
            ->values()
            ->all();

        $recentUsers = $rows
            ->sortBy('joinedSort')
            ->take(5)
            ->values()
            ->all();

        $attentionUsers = $rows
            ->filter(fn (array $row) => count($row['flags']) > 0)
            ->sortBy([
                ['setupComplete', 'asc'],
                ['sessionCount', 'asc'],
                ['joinedSort', 'asc'],
            ])
            ->take(6)
            ->values()
            ->all();

        return [
            'overviewCards' => $overviewCards,
            'healthCards' => $healthCards,
            'funnel' => $funnel,
            'signupTrend' => $signupTrend,
            'sessionTiers' => $sessionTiers,
            'recentUsers' => $recentUsers,
            'attentionUsers' => $attentionUsers,
            'users' => $sortedUsers->all(),
        ];
    }

    private function mapUser(User $user, CarbonInterface $now): array
    {
        /** @var Profile|null $profile */
        $profile = $user->ownedProfiles->sortBy('id')->first();
        $sessions = $profile?->sessions?->sortByDesc(fn ($session) => $session->session_date?->getTimestamp() ?? 0)->values() ?? collect();
        $settings = $profile->settings ?? [];
        $sessionCount = $sessions->count();
        $observationCount = $sessions->filter(fn ($session) => filled($session->notes_public))->count();
        $importedSessionCount = $sessions->filter(fn ($session) => filled($session->external_ref))->count();
        $setupComplete = filled(data_get($settings, 'setup_completed_at'));

        $lastSessionDate = $sessions->first()?->session_date;
        $lastSessionDateLabel = $lastSessionDate?->format('j M Y');

        $lastActivityAt = collect([
            $user->updated_at,
            $profile?->updated_at,
            $sessions
                ->map(fn ($session) => $session->updated_at)
                ->filter()
                ->sortByDesc(fn (CarbonInterface $date) => $date->getTimestamp())
                ->first(),
        ])
            ->filter()
            ->sortByDesc(fn (CarbonInterface $date) => $date->getTimestamp())
            ->first();

        $lastActivityDaysAgo = $lastActivityAt ? $lastActivityAt->diffInDays($now) : null;

        $flags = array_values(array_filter([
            ! $setupComplete ? 'Setup incomplete' : null,
            $sessionCount === 0 ? 'No sessions yet' : null,
            $sessionCount > 0 && $observationCount === 0 ? 'No observations yet' : null,
            $lastActivityDaysAgo !== null && $lastActivityDaysAgo > 60 ? 'Dormant 60d' : null,
            $importedSessionCount > 0 ? 'Uses Garmin import' : null,
        ]));

        return [
            'id' => $user->id,
            'name' => (string) data_get($settings, 'paddler_name', $profile?->name ?: $user->name),
            'accountName' => $user->name,
            'email' => $user->email,
            'homeWater' => $profile?->home_water,
            'kayakClub' => data_get($settings, 'kayak_club'),
            'setupComplete' => $setupComplete,
            'sessionCount' => $sessionCount,
            'observationCount' => $observationCount,
            'importedSessionCount' => $importedSessionCount,
            'joinedDate' => $user->created_at->format('j M Y'),
            'joinedRelative' => $user->created_at->diffForHumans($now, true).' ago',
            'joinedMonth' => $user->created_at->format('Y-m'),
            'joinedDaysAgo' => $user->created_at->diffInDays($now),
            'joinedSort' => -1 * $user->created_at->getTimestamp(),
            'lastSessionDate' => $lastSessionDateLabel,
            'lastActivity' => $lastActivityAt?->diffForHumans($now, true).' ago',
            'lastActivityDaysAgo' => $lastActivityDaysAgo,
            'lastActivitySort' => $lastActivityDaysAgo ?? 999999,
            'flags' => $flags,
        ];
    }
}
