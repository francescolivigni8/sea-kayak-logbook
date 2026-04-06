<?php

namespace App\Support;

use App\Models\PaddleSession;
use App\Models\Profile;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProfileDashboardData
{
    public function __construct(
        private readonly SessionMediaService $media,
    ) {}

    public function build(Profile $profile, Collection $sessions, bool $publicView = false): array
    {
        $sessions = $sessions->values();
        $now = now($profile->timezone);
        $totalDistance = round((float) $sessions->sum('distance_km'), 1);
        $totalMinutes = (int) $sessions->sum('duration_minutes');
        $sessionCount = $sessions->count();
        $longestDistance = round((float) $sessions->max('distance_km'), 1);
        $trackSessions = (int) $sessions->filter(fn (PaddleSession $session) => $this->hasTrackData($session))->count();
        $paddledMonths = (int) $sessions
            ->filter(fn (PaddleSession $session) => $session->session_date !== null)
            ->map(fn (PaddleSession $session) => $session->session_date->format('Y-m'))
            ->unique()
            ->count();

        $expeditionSessions = $sessions
            ->filter(fn (PaddleSession $session) => $session->is_expedition)
            ->values();
        $expeditionPlaces = $this->buildExpeditionPlaces($profile, $expeditionSessions, $publicView);

        return [
            'profile' => [
                'name' => $profile->name,
                'slug' => $profile->slug,
                'homeWater' => $profile->home_water,
                'timezone' => $profile->timezone,
                'isPublic' => $profile->is_public,
                'publicPath' => '/p/'.$profile->slug,
            ],
            'headline' => [
                'sessionCount' => $sessionCount,
                'distanceKm' => $totalDistance,
                'durationHours' => round($totalMinutes / 60, 1),
                'longestDistanceKm' => $longestDistance,
                'averageDistanceKm' => $sessionCount > 0 ? round($totalDistance / $sessionCount, 1) : 0.0,
                'trackSessions' => $trackSessions,
                'paddledMonths' => $paddledMonths,
            ],
            'yearSnapshots' => $this->buildYearSnapshots($sessions, $now),
            'monthlyDistance' => $this->buildMonthlyDistance($sessions, $now),
            'routeMix' => $this->buildRouteMix($sessions, $totalDistance),
            'dataCoverage' => $this->buildDataCoverage($sessions),
            'seaState' => $this->buildSeaState($sessions),
            'mapData' => $this->buildMapData($profile, $sessions),
            'expeditionSummary' => $this->buildExpeditionSummary($expeditionSessions),
            'expeditionPlaces' => $expeditionPlaces,
            'expeditionMapData' => $this->buildExpeditionMapData($expeditionPlaces),
            'recentSessions' => $sessions
                ->take(6)
                ->map(fn (PaddleSession $session) => $this->mapRecentSession($session))
                ->values(),
            'meta' => [
                'publicView' => $publicView,
            ],
        ];
    }

    public function buildExpeditionAtlas(Profile $profile, Collection $sessions, bool $publicView = false): array
    {
        $expeditionSessions = $sessions
            ->filter(fn (PaddleSession $session) => $session->is_expedition)
            ->values();
        $expeditionPlaces = $this->buildExpeditionPlaces($profile, $expeditionSessions, $publicView);

        return [
            'profile' => [
                'name' => $profile->name,
                'slug' => $profile->slug,
                'homeWater' => $profile->home_water,
                'timezone' => $profile->timezone,
                'isPublic' => $profile->is_public,
                'publicPath' => '/p/'.$profile->slug,
            ],
            'expeditionSummary' => $this->buildExpeditionSummary($expeditionSessions),
            'expeditionPlaces' => $expeditionPlaces,
            'expeditionMapData' => $this->buildExpeditionMapData($expeditionPlaces),
            'meta' => [
                'publicView' => $publicView,
            ],
        ];
    }

    public function buildExpeditionPlacePage(Profile $profile, Collection $sessions, string $placeSlug, bool $publicView = false): ?array
    {
        $expeditionSessions = $sessions
            ->filter(fn (PaddleSession $session) => $session->is_expedition)
            ->values();

        $place = collect($this->buildExpeditionPlaces($profile, $expeditionSessions, $publicView))
            ->firstWhere('slug', $placeSlug);

        if (! $place) {
            return null;
        }

        $placeSessions = $expeditionSessions
            ->filter(fn (PaddleSession $session) => $this->expeditionGroupKey($session) === $place['groupKey'])
            ->sortByDesc(fn (PaddleSession $session) => $session->session_date?->toDateString() ?? '')
            ->values();

        $mapPalette = $this->mapPalette();

        $routes = $placeSessions
            ->filter(fn (PaddleSession $session) => is_array($session->route_profile) && count($session->route_profile) > 1)
            ->values()
            ->map(function (PaddleSession $session, int $index) use ($mapPalette) {
                return [
                    'id' => $session->id,
                    'label' => trim(($session->session_date?->format('d M Y') ?? 'Session').' · '.$session->title),
                    'color' => $mapPalette[$index % count($mapPalette)],
                    'year' => $session->session_date?->year,
                    'years' => $session->session_date?->year ? [$session->session_date->year] : [],
                    'isExpedition' => true,
                    'category' => 'expedition',
                    'points' => collect($session->route_profile)
                        ->filter(fn ($point) => isset($point['lat'], $point['lng']))
                        ->map(fn ($point) => [
                            'lat' => (float) $point['lat'],
                            'lng' => (float) $point['lng'],
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        $pins = $placeSessions
            ->filter(fn (PaddleSession $session) => ! (is_array($session->route_profile) && count($session->route_profile) > 1))
            ->values()
            ->map(function (PaddleSession $session, int $index) use ($mapPalette) {
                $point = $this->expeditionReferencePoint($session);

                if (! $point) {
                    return null;
                }

                return [
                    'id' => 'place-pin-'.$session->id,
                    'label' => trim(($session->session_date?->format('d M Y') ?? 'Session').' · '.$session->title),
                    'color' => $mapPalette[$index % count($mapPalette)],
                    'year' => $session->session_date?->year,
                    'years' => $session->session_date?->year ? [$session->session_date->year] : [],
                    'isExpedition' => true,
                    'category' => 'expedition',
                    'count' => 1,
                    'lat' => $point['lat'],
                    'lng' => $point['lng'],
                ];
            })
            ->filter()
            ->values()
            ->all();

        $photos = $placeSessions
            ->filter(fn (PaddleSession $session) => filled($session->session_photo_path))
            ->map(fn (PaddleSession $session) => [
                'id' => $session->id,
                'title' => $session->title,
                'date' => $session->session_date?->format('d M Y'),
                'url' => $this->media->url($session->session_photo_path),
                'name' => $session->session_photo_name,
                'notes' => $publicView ? ($session->notes_public ?: $session->expedition_notes) : ($session->expedition_notes ?: $session->notes_public ?: $session->notes_private),
            ])
            ->values()
            ->all();

        $sessionCards = $placeSessions
            ->map(fn (PaddleSession $session) => [
                'id' => $session->id,
                'title' => $session->title,
                'date' => $session->session_date?->format('d M Y'),
                'distanceKm' => round((float) $session->distance_km, 1),
                'durationMinutes' => (int) $session->duration_minutes,
                'daysOut' => (int) ($session->expedition_days ?? 0),
                'launchName' => $session->launch_name,
                'routeCategoryLabel' => $this->routeCategoryLabel($session->route_category),
                'beaufort' => $session->wind_beaufort,
                'photoUrl' => $this->media->url($session->session_photo_path),
                'notes' => $publicView ? ($session->notes_public ?: $session->expedition_notes) : ($session->expedition_notes ?: $session->notes_public ?: $session->notes_private),
                'path' => $publicView ? null : route('sessions.show', $session),
            ])
            ->values()
            ->all();

        return [
            'profile' => [
                'name' => $profile->name,
                'slug' => $profile->slug,
                'homeWater' => $profile->home_water,
                'timezone' => $profile->timezone,
                'isPublic' => $profile->is_public,
                'publicPath' => '/p/'.$profile->slug,
            ],
            'place' => [
                'slug' => $place['slug'],
                'label' => $place['label'],
                'tripCount' => $place['tripCount'],
                'distanceKm' => $place['distanceKm'],
                'daysOut' => $place['daysOut'],
                'latestDate' => $place['latestDate'],
                'photoUrl' => $place['photoUrl'],
                'path' => $place['path'],
                'publicPath' => $place['publicPath'],
            ],
            'mapData' => [
                'defaultView' => [
                    'lat' => $place['lat'],
                    'lng' => $place['lng'],
                    'zoom' => 8,
                ],
                'routes' => $routes,
                'pins' => $pins,
            ],
            'photos' => $photos,
            'sessions' => $sessionCards,
            'meta' => [
                'publicView' => $publicView,
            ],
        ];
    }

    private function buildYearSnapshots(Collection $sessions, CarbonInterface $now): array
    {
        $currentYear = $now->year;
        $rollingStart = $now->copy()->subMonths(11)->startOfMonth();

        $thisYearDistance = round((float) $sessions
            ->filter(fn (PaddleSession $session) => $session->session_date?->year === $currentYear)
            ->sum('distance_km'), 1);

        $rollingDistance = round((float) $sessions
            ->filter(fn (PaddleSession $session) => $session->session_date && $session->session_date->greaterThanOrEqualTo($rollingStart))
            ->sum('distance_km'), 1);

        $expeditionDistance = round((float) $sessions
            ->filter(fn (PaddleSession $session) => $session->is_expedition)
            ->sum('distance_km'), 1);

        return [
            [
                'label' => 'All time',
                'value' => round((float) $sessions->sum('distance_km'), 1),
                'unit' => 'km',
                'detail' => $sessions->count().' sessions recorded',
            ],
            [
                'label' => (string) $currentYear,
                'value' => $thisYearDistance,
                'unit' => 'km',
                'detail' => $sessions
                    ->filter(fn (PaddleSession $session) => $session->session_date?->year === $currentYear)
                    ->count().' sessions this year',
            ],
            [
                'label' => 'Rolling 12 months',
                'value' => $rollingDistance,
                'unit' => 'km',
                'detail' => 'Live moving window',
            ],
            [
                'label' => 'Expedition total',
                'value' => $expeditionDistance,
                'unit' => 'km',
                'detail' => (int) $sessions->filter(fn (PaddleSession $session) => $session->is_expedition)->sum('expedition_days').' days out',
            ],
        ];
    }

    private function buildMonthlyDistance(Collection $sessions, CarbonInterface $now): array
    {
        $grouped = $sessions
            ->filter(fn (PaddleSession $session) => $session->session_date !== null)
            ->groupBy(fn (PaddleSession $session) => $session->session_date->format('Y-m'));

        return collect(range(11, 0))
            ->map(function (int $offset) use ($grouped, $now) {
                $month = $now->copy()->subMonths($offset);
                $key = $month->format('Y-m');
                $distance = round((float) ($grouped->get($key)?->sum('distance_km') ?? 0), 1);

                return [
                    'key' => $key,
                    'label' => strtoupper($month->format('M')),
                    'distanceKm' => $distance,
                ];
            })
            ->values()
            ->all();
    }

    private function buildRouteMix(Collection $sessions, float $totalDistance): array
    {
        $labels = [
            'journey' => 'Journey',
            'training' => 'Training',
            'benchmark' => 'Benchmark',
            'navigation' => 'Navigation',
            'rescue-practice' => 'Rescue practice',
            'expedition' => 'Expedition',
        ];

        $tones = [
            'journey' => 'sky',
            'training' => 'violet',
            'benchmark' => 'amber',
            'navigation' => 'emerald',
            'rescue-practice' => 'rose',
            'expedition' => 'indigo',
        ];

        return $sessions
            ->groupBy(fn (PaddleSession $session) => $session->route_category ?: 'journey')
            ->map(function (Collection $group, string $category) use ($labels, $tones, $totalDistance) {
                $distance = round((float) $group->sum('distance_km'), 1);

                return [
                    'key' => $category,
                    'label' => $labels[$category] ?? ucfirst(str_replace('-', ' ', $category)),
                    'sessionCount' => $group->count(),
                    'distanceKm' => $distance,
                    'share' => $totalDistance > 0 ? round(($distance / $totalDistance) * 100, 1) : 0.0,
                    'tone' => $tones[$category] ?? 'slate',
                ];
            })
            ->sortByDesc('distanceKm')
            ->values()
            ->all();
    }

    private function buildDataCoverage(Collection $sessions): array
    {
        $sessionCount = max($sessions->count(), 1);

        $items = [
            [
                'label' => 'Track data attached',
                'count' => (int) $sessions->filter(fn (PaddleSession $session) => $this->hasTrackData($session))->count(),
                'tone' => 'cyan',
            ],
            [
                'label' => 'Sea conditions logged',
                'count' => (int) $sessions->filter(fn (PaddleSession $session) => $session->conditions_logged)->count(),
                'tone' => 'emerald',
            ],
            [
                'label' => 'Development logged',
                'count' => (int) $sessions->filter(fn (PaddleSession $session) => $session->development_logged)->count(),
                'tone' => 'amber',
            ],
            [
                'label' => 'Public sessions',
                'count' => (int) $sessions->filter(fn (PaddleSession $session) => $session->is_public)->count(),
                'tone' => 'violet',
            ],
        ];

        return collect($items)
            ->map(fn (array $item) => [
                ...$item,
                'percent' => round(($item['count'] / $sessionCount) * 100),
            ])
            ->all();
    }

    private function buildSeaState(Collection $sessions): array
    {
        $beaufortBase = collect(['F0', 'F1', 'F2', 'F3', 'F4', 'F5', 'F6', 'F7+'])
            ->map(fn (string $label) => ['label' => $label, 'count' => 0])
            ->keyBy('label');

        foreach ($sessions as $session) {
            if ($session->wind_beaufort === null) {
                continue;
            }

            $label = $session->wind_beaufort >= 7 ? 'F7+' : 'F'.$session->wind_beaufort;
            $current = $beaufortBase->get($label);
            $beaufortBase->put($label, [
                'label' => $label,
                'count' => ($current['count'] ?? 0) + 1,
            ]);
        }

        $tideOrder = ['slack', 'flooding', 'high', 'ebbing', 'low'];
        $tideStates = collect($tideOrder)->map(function (string $state) use ($sessions) {
            return [
                'label' => ucfirst($state),
                'count' => (int) $sessions->filter(fn (PaddleSession $session) => $session->tide_state === $state)->count(),
            ];
        })->all();

        $severities = ['low', 'moderate', 'high', 'extreme'];
        $conditionFields = [
            'Rain' => 'rain_severity',
            'Wind' => 'wind_severity',
            'Temperature' => 'temperature_severity',
            'Forecast' => 'forecast_severity',
        ];

        $conditionMatrix = collect($conditionFields)->map(function (string $field, string $label) use ($sessions, $severities) {
            return [
                'label' => $label,
                'values' => collect($severities)->map(function (string $severity) use ($sessions, $field) {
                    return [
                        'key' => $severity,
                        'count' => (int) $sessions->filter(fn (PaddleSession $session) => strtolower((string) $session->{$field}) === $severity)->count(),
                    ];
                })->all(),
            ];
        })->values()->all();

        $airTemps = $sessions->pluck('air_temp_c')->filter(fn ($value) => $value !== null)->map(fn ($value) => (float) $value);
        $seaTemps = $sessions->pluck('sea_temp_c')->filter(fn ($value) => $value !== null)->map(fn ($value) => (float) $value);

        return [
            'beaufortBands' => $beaufortBase->values()->all(),
            'tideStates' => $tideStates,
            'conditionMatrix' => $conditionMatrix,
            'rescueTotals' => [
                [
                    'label' => 'Successful rolls',
                    'count' => (int) $sessions->sum('successful_rolls_count'),
                ],
                [
                    'label' => 'Wet exits',
                    'count' => (int) $sessions->sum('wet_exits_count'),
                ],
                [
                    'label' => 'Tow rescues',
                    'count' => (int) $sessions->sum('tow_rescues_count'),
                ],
            ],
            'temperatureAverages' => [
                'air' => $airTemps->count() ? round((float) $airTemps->avg(), 1) : null,
                'sea' => $seaTemps->count() ? round((float) $seaTemps->avg(), 1) : null,
            ],
        ];
    }

    private function buildMapData(Profile $profile, Collection $sessions): array
    {
        $palette = $this->mapPalette();

        $routes = $sessions
            ->filter(fn (PaddleSession $session) => is_array($session->route_profile) && count($session->route_profile) > 1)
            ->values()
            ->map(function (PaddleSession $session, int $index) use ($palette) {
                return [
                    'id' => $session->id,
                    'label' => trim(($session->session_date?->format('d M Y') ?? 'Session').' · '.$session->title),
                    'color' => $palette[$index % count($palette)],
                    'year' => $session->session_date?->year,
                    'years' => $session->session_date?->year ? [$session->session_date->year] : [],
                    'isExpedition' => (bool) $session->is_expedition,
                    'category' => $session->route_category ?: 'journey',
                    'points' => collect($session->route_profile)
                        ->filter(fn ($point) => isset($point['lat'], $point['lng']))
                        ->map(fn ($point) => [
                            'lat' => (float) $point['lat'],
                            'lng' => (float) $point['lng'],
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->filter(fn (array $route) => count($route['points']) > 1)
            ->values()
            ->all();

        $pins = $sessions
            ->filter(fn (PaddleSession $session) => filled($session->launch_lat) && filled($session->launch_lng) && ! (is_array($session->route_profile) && count($session->route_profile) > 1))
            ->values()
            ->map(function (PaddleSession $session, int $index) use ($palette) {
                return [
                    'id' => 'pin-'.$session->id,
                    'label' => trim(($session->launch_name ?: $profile->home_water).' · '.$session->title),
                    'color' => $palette[$index % count($palette)],
                    'year' => $session->session_date?->year,
                    'years' => $session->session_date?->year ? [$session->session_date->year] : [],
                    'isExpedition' => (bool) $session->is_expedition,
                    'category' => $session->route_category ?: 'journey',
                    'count' => 1,
                    'lat' => (float) $session->launch_lat,
                    'lng' => (float) $session->launch_lng,
                ];
            })
            ->all();

        return [
            'defaultView' => $this->defaultViewFor($profile),
            'routes' => $routes,
            'pins' => $pins,
        ];
    }

    private function buildExpeditionSummary(Collection $expeditionSessions): array
    {
        return [
            'distanceKm' => round((float) $expeditionSessions->sum('distance_km'), 1),
            'daysOut' => (int) $expeditionSessions->sum('expedition_days'),
            'tripCount' => $expeditionSessions->count(),
        ];
    }

    public function buildExpeditionPlaces(Profile $profile, Collection $expeditionSessions, bool $publicView = false): array
    {
        $palette = $this->mapPalette();

        return $expeditionSessions
            ->map(function (PaddleSession $session) {
                $point = $this->expeditionReferencePoint($session);

                if (! $point) {
                    return null;
                }

                $label = trim((string) ($session->launch_name ?: $session->area_name ?: $session->title));
                $groupKey = $this->expeditionGroupKey($session);
                $year = $session->session_date?->year;

                return [
                    'groupKey' => $groupKey,
                    'label' => $label ?: 'Expedition location',
                    'lat' => (float) $point['lat'],
                    'lng' => (float) $point['lng'],
                    'year' => $year,
                    'distanceKm' => (float) $session->distance_km,
                    'daysOut' => (int) ($session->expedition_days ?? 0),
                    'session' => $session,
                ];
            })
            ->filter()
            ->groupBy('groupKey')
            ->values()
            ->map(function (Collection $group, int $index) use ($palette, $profile, $publicView) {
                $first = $group->first();
                $tripCount = $group->count();
                $years = $group
                    ->pluck('year')
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();
                /** @var PaddleSession|null $photoSession */
                $photoSession = $group
                    ->pluck('session')
                    ->first(fn (PaddleSession $session) => filled($session->session_photo_path));
                $slug = $this->expeditionPlaceSlug($first['groupKey'], $first['label']);

                return [
                    'groupKey' => $first['groupKey'],
                    'slug' => $slug,
                    'label' => $first['label'],
                    'color' => $palette[$index % count($palette)],
                    'lat' => round((float) $group->avg('lat'), 6),
                    'lng' => round((float) $group->avg('lng'), 6),
                    'tripCount' => $tripCount,
                    'countsByYear' => $group
                        ->filter(fn (array $item) => $item['year'])
                        ->countBy('year')
                        ->mapWithKeys(fn (int $count, int|string $year) => [(string) $year => $count])
                        ->all(),
                    'year' => $years ? max($years) : null,
                    'years' => $years,
                    'distanceKm' => round((float) $group->sum('distanceKm'), 1),
                    'daysOut' => (int) $group->sum('daysOut'),
                    'latestDate' => optional($group->pluck('session')->filter()->sortByDesc(fn (PaddleSession $session) => $session->session_date?->toDateString() ?? '')->first()?->session_date)->format('d M Y'),
                    'photoUrl' => $this->media->url($photoSession?->session_photo_path),
                    'path' => $publicView
                        ? route('profiles.public.expeditions.show', ['profile' => $profile, 'place' => $slug])
                        : route('expeditions.show', ['place' => $slug]),
                    'publicPath' => route('profiles.public.expeditions.show', ['profile' => $profile, 'place' => $slug]),
                ];
            })
            ->values()
            ->all();
    }

    private function buildExpeditionMapData(array $expeditionPlaces): array
    {
        $pins = collect($expeditionPlaces)
            ->map(fn (array $place, int $index) => [
                'id' => 'expedition-pin-'.$index,
                'label' => $place['label'],
                'color' => $place['color'],
                'lat' => $place['lat'],
                'lng' => $place['lng'],
                'count' => $place['tripCount'],
                'countsByYear' => $place['countsByYear'],
                'year' => $place['year'],
                'years' => $place['years'],
                'isExpedition' => true,
                'category' => 'expedition',
                'distanceKm' => $place['distanceKm'],
                'daysOut' => $place['daysOut'],
                'path' => $place['path'],
            ])
            ->all();

        return [
            'defaultView' => [
                'lat' => 20.0,
                'lng' => 0.0,
                'zoom' => 2,
            ],
            'routes' => [],
            'pins' => $pins,
        ];
    }

    private function mapRecentSession(PaddleSession $session): array
    {
        return [
            'id' => $session->id,
            'title' => $session->title,
            'date' => $session->session_date?->format('d M Y'),
            'distanceKm' => round((float) $session->distance_km, 1),
            'durationMinutes' => (int) $session->duration_minutes,
            'routeCategoryLabel' => $this->routeCategoryLabel($session->route_category),
            'launchName' => $session->launch_name,
            'beaufort' => $session->wind_beaufort,
            'isPublic' => (bool) $session->is_public,
            'hasTrack' => $this->hasTrackData($session),
            'isExpedition' => (bool) $session->is_expedition,
        ];
    }

    private function routeCategoryLabel(?string $category): string
    {
        return match ($category) {
            'benchmark' => 'Benchmark',
            'training' => 'Training',
            'journey' => 'Journey',
            'navigation' => 'Navigation',
            'rescue-practice' => 'Rescue practice',
            'expedition' => 'Expedition',
            default => ucfirst(str_replace('-', ' ', (string) $category)),
        };
    }

    private function defaultViewFor(Profile $profile): array
    {
        return match (strtolower($profile->home_water)) {
            'faxafloi' => ['lat' => 64.1670, 'lng' => -21.8210, 'zoom' => 10],
            'reykjavik' => ['lat' => 64.1466, 'lng' => -21.9426, 'zoom' => 11],
            default => ['lat' => 64.1466, 'lng' => -21.9426, 'zoom' => 10],
        };
    }

    private function expeditionReferencePoint(PaddleSession $session): ?array
    {
        if (is_array($session->route_profile) && count($session->route_profile) > 0) {
            $firstPoint = collect($session->route_profile)
                ->first(fn ($point) => isset($point['lat'], $point['lng']));

            if ($firstPoint) {
                return [
                    'lat' => (float) $firstPoint['lat'],
                    'lng' => (float) $firstPoint['lng'],
                ];
            }
        }

        if (filled($session->launch_lat) && filled($session->launch_lng)) {
            return [
                'lat' => (float) $session->launch_lat,
                'lng' => (float) $session->launch_lng,
            ];
        }

        if (filled($session->landing_lat) && filled($session->landing_lng)) {
            return [
                'lat' => (float) $session->landing_lat,
                'lng' => (float) $session->landing_lng,
            ];
        }

        return null;
    }

    private function hasTrackData(PaddleSession $session): bool
    {
        return filled($session->gpx_path)
            || filled($session->fit_path)
            || (is_array($session->route_profile) && count($session->route_profile) > 1);
    }

    private function expeditionGroupKey(PaddleSession $session): string
    {
        $point = $this->expeditionReferencePoint($session);
        $label = trim((string) ($session->launch_name ?: $session->area_name ?: $session->title));

        return strtolower($label ?: sprintf('%.2f:%.2f', $point['lat'] ?? 0, $point['lng'] ?? 0));
    }

    private function expeditionPlaceSlug(string $groupKey, string $label): string
    {
        $base = Str::slug($label) ?: 'expedition-place';

        return $base.'-'.substr(md5($groupKey), 0, 6);
    }

    private function mapPalette(): array
    {
        return [
            '#4f46e5',
            '#0ea5e9',
            '#14b8a6',
            '#f97316',
            '#ec4899',
            '#84cc16',
            '#8b5cf6',
            '#f59e0b',
        ];
    }
}
