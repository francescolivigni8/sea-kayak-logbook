<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Models\PaddleSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ImportBatchController extends Controller
{
    public function index(Request $request): Response
    {
        $profile = $request->user()->resolveActiveProfile();

        $batches = $profile->importBatches()
            ->with(['items' => fn ($query) => $query->orderBy('csv_row')->orderBy('id')])
            ->latest()
            ->limit(25)
            ->get()
            ->map(fn (ImportBatch $batch): array => [
                'id' => $batch->id,
                'kind' => $batch->kind,
                'fileName' => $batch->file_name,
                'status' => $batch->status,
                'rowsCount' => $batch->rows_count,
                'selectedCount' => $batch->selected_count,
                'createdCount' => $batch->created_count,
                'updatedCount' => $batch->updated_count,
                'skippedCount' => $batch->skipped_count,
                'summary' => $batch->summary ?? [],
                'createdAt' => $batch->created_at?->toDateTimeString(),
                'undoneAt' => $batch->undone_at?->toDateTimeString(),
                'items' => $batch->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'sessionId' => $item->paddle_session_id,
                    'csvRow' => $item->csv_row,
                    'action' => $item->action,
                    'title' => $item->title,
                    'date' => $item->session_date?->toDateString(),
                    'distanceKm' => $item->distance_km,
                    'durationMinutes' => $item->duration_minutes,
                ])->all(),
            ]);

        return Inertia::render('imports/History', [
            'batches' => $batches,
            'maintenance' => [
                'duplicateGroups' => $this->duplicateGroups($profile->sessions()->get())->values(),
                'csvOnlySessions' => $this->csvOnlySessions($profile)->values(),
            ],
        ]);
    }

    public function undo(Request $request, ImportBatch $batch): RedirectResponse
    {
        $profile = $request->user()->resolveActiveProfile();
        abort_unless($batch->profile_id === $profile->id, 404);

        if ($batch->undone_at) {
            return back()->with('success', 'That import was already undone.');
        }

        DB::transaction(function () use ($batch, $request, $profile): void {
            $batch->load(['items' => fn ($query) => $query->latest('id')]);

            foreach ($batch->items as $item) {
                if ($item->action === 'created' && $item->paddle_session_id) {
                    PaddleSession::query()
                        ->where('profile_id', $profile->id)
                        ->where('id', $item->paddle_session_id)
                        ->delete();

                    continue;
                }

                if ($item->action === 'updated' && $item->paddle_session_id && is_array($item->before_snapshot)) {
                    $session = PaddleSession::query()
                        ->where('profile_id', $profile->id)
                        ->where('id', $item->paddle_session_id)
                        ->first();

                    if ($session) {
                        $session->forceFill($item->before_snapshot);
                        $session->save();
                    }
                }
            }

            $batch->forceFill([
                'undone_at' => now(),
                'undone_by_user_id' => $request->user()->id,
            ])->save();
        });

        return back()->with('success', 'Import undone. Created rows were deleted and updated rows were restored.');
    }

    public function mergeDuplicates(Request $request): RedirectResponse
    {
        $profile = $request->user()->resolveActiveProfile();
        $groups = $this->duplicateGroups($profile->sessions()->get());
        $deleted = 0;

        DB::transaction(function () use ($groups, &$deleted): void {
            foreach ($groups as $group) {
                $keepId = (int) $group['keep']['id'];
                $deleteIds = collect($group['sessions'])
                    ->pluck('id')
                    ->reject(fn (int $id): bool => $id === $keepId)
                    ->values();

                if ($deleteIds->isEmpty()) {
                    continue;
                }

                $deleted += PaddleSession::query()
                    ->whereIn('id', $deleteIds)
                    ->delete();
            }
        });

        return back()->with('success', "Merged duplicate groups. Deleted {$deleted} duplicate sessions.");
    }

    public function deleteCsvOnly(Request $request): RedirectResponse
    {
        $profile = $request->user()->resolveActiveProfile();
        $ids = $this->csvOnlySessions($profile)->pluck('id');

        if ($ids->isEmpty()) {
            return back()->with('success', 'No CSV-only sessions without route data were found.');
        }

        $deleted = PaddleSession::query()
            ->where('profile_id', $profile->id)
            ->whereIn('id', $ids)
            ->delete();

        return back()->with('success', "Deleted {$deleted} CSV-only sessions without route data.");
    }

    public function export(Request $request): JsonResponse
    {
        $profile = $request->user()->resolveActiveProfile();
        $sessions = $profile->sessions()
            ->with('categories')
            ->orderBy('session_date')
            ->orderBy('start_at')
            ->get()
            ->map(fn (PaddleSession $session): array => [
                'id' => $session->id,
                'attributes' => $session->getAttributes(),
                'categories' => $session->categories->map(fn ($category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ])->all(),
            ]);

        return response()
            ->json([
                'exportedAt' => now()->toIso8601String(),
                'profile' => [
                    'id' => $profile->id,
                    'name' => $profile->name,
                    'slug' => $profile->slug,
                    'timezone' => $profile->timezone,
                    'homeWater' => $profile->home_water,
                ],
                'sessions' => $sessions,
                'importBatches' => $profile->importBatches()->with('items')->latest()->get(),
            ])
            ->header('Content-Disposition', 'attachment; filename="sea-kayak-logbook-backup.json"');
    }

    private function csvOnlySessions($profile)
    {
        return $profile->sessions()
            ->where('external_ref', 'like', 'garmin:%')
            ->where(function ($query): void {
                $query->whereNull('route_points')->orWhere('route_points', '');
            })
            ->where(function ($query): void {
                $query->whereNull('gpx_path')->orWhere('gpx_path', '');
            })
            ->where(function ($query): void {
                $query->whereNull('fit_path')->orWhere('fit_path', '');
            })
            ->orderBy('session_date')
            ->get(['id', 'session_date', 'start_at', 'title', 'distance_km', 'duration_minutes'])
            ->map(fn (PaddleSession $session): array => [
                'id' => $session->id,
                'date' => $session->session_date?->toDateString(),
                'start' => $session->start_at?->format('H:i'),
                'title' => $session->title,
                'distanceKm' => round((float) $session->distance_km, 2),
                'durationMinutes' => (int) $session->duration_minutes,
            ]);
    }

    private function duplicateGroups($sessions)
    {
        return $sessions
            ->groupBy(fn (PaddleSession $session): string => implode('|', [
                $session->session_date?->toDateString(),
                $session->start_at?->copy()->second(0)->format('H:i') ?? 'no-start',
                number_format(round((float) $session->distance_km, 1), 1, '.', ''),
            ]))
            ->filter(fn ($group): bool => $group->count() > 1)
            ->map(function ($group): array {
                $ranked = $group
                    ->sortByDesc(fn (PaddleSession $session): int => $this->qualityScore($session))
                    ->values();

                return [
                    'key' => $this->duplicateLabel($ranked->first()),
                    'keep' => $this->maintenanceSession($ranked->first()),
                    'sessions' => $ranked->map(fn (PaddleSession $session): array => $this->maintenanceSession($session))->all(),
                ];
            });
    }

    private function duplicateLabel(PaddleSession $session): string
    {
        return implode(' · ', [
            $session->session_date?->toDateString(),
            number_format((float) $session->distance_km, 1).' km',
        ]);
    }

    private function maintenanceSession(PaddleSession $session): array
    {
        return [
            'id' => $session->id,
            'date' => $session->session_date?->toDateString(),
            'start' => $session->start_at?->format('H:i'),
            'title' => $session->title,
            'distanceKm' => round((float) $session->distance_km, 2),
            'durationMinutes' => (int) $session->duration_minutes,
            'hasRoute' => filled($session->route_points) || (is_array($session->route_profile) && count($session->route_profile) > 1),
            'hasGpx' => filled($session->garmin_gpx_name) || filled($session->gpx_path),
            'hasFit' => filled($session->garmin_fit_name) || filled($session->fit_path),
        ];
    }

    private function qualityScore(PaddleSession $session): int
    {
        return 0
            + (filled($session->route_points) ? 100 : 0)
            + (filled($session->garmin_gpx_name) ? 50 : 0)
            + (filled($session->fit_path) ? 40 : 0)
            + (filled($session->route_profile) && $session->route_profile !== [] ? 25 : 0)
            + (filled($session->notes_private) ? 5 : 0)
            + (filled($session->notes_public) ? 5 : 0);
    }
}
