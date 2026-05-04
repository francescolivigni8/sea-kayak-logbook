<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $session['title'] }} | Share session</title>
    @php
        $units = \App\Support\UnitFormat::fromPreferences($unitPreferences ?? []);

        $formatDuration = function (?int $minutes): string {
            if ($minutes === null || $minutes <= 0) {
                return '-';
            }

            $hours = intdiv($minutes, 60);
            $remainingMinutes = $minutes % 60;

            if ($hours > 0 && $remainingMinutes > 0) {
                return sprintf('%dh %02dm', $hours, $remainingMinutes);
            }

            if ($hours > 0) {
                return sprintf('%dh', $hours);
            }

            return sprintf('%dm', $remainingMinutes);
        };

        $chipValues = array_values(array_filter([
            $session['routeCategoryLabel'] ?? null,
            ...collect($session['folders'] ?? [])->pluck('name')->map(fn (string $name) => 'Folder: '.$name)->all(),
            $session['bodyOfWater'] ?? null,
            $session['beaufort'] !== null ? 'F'.$session['beaufort'] : null,
            filled($session['kayakUsed'] ?? null) ? 'Kayak: '.$session['kayakUsed'] : null,
            filled($session['paddleUsed'] ?? null) ? 'Paddle: '.$session['paddleUsed'] : null,
            !empty($session['isExpedition']) ? 'Expedition' : null,
        ], fn ($value) => filled($value)));

        $keyStats = [
            ['label' => 'Distance', 'value' => $units->formatDistanceKm(isset($session['distanceKm']) ? (float) $session['distanceKm'] : null)],
            ['label' => 'Duration', 'value' => $formatDuration($session['durationMinutes'] ?? null)],
            ['label' => 'Avg speed', 'value' => $units->formatSpeedKmh(isset($session['averageSpeedKmh']) ? (float) $session['averageSpeedKmh'] : null)],
            ['label' => 'Wind', 'value' => isset($session['beaufort']) && $session['beaufort'] !== null ? 'F'.$session['beaufort'] : $units->formatWindMs(isset($session['windAvgMs']) ? (float) $session['windAvgMs'] : null)],
        ];

        $journeyFacts = array_values(array_filter([
            ['label' => 'Date', 'value' => $session['date'] ?? null],
            ['label' => 'Start', 'value' => filled($session['startTimeLocal'] ?? null) ? trim(($session['startTimeLocal'] ?? '').' '.($session['timezone'] ?? '')) : null],
            ['label' => 'Area', 'value' => $session['areaName'] ?? ($profile['homeWater'] ?? null)],
            ['label' => 'Launch', 'value' => $session['launchName'] ?? null],
            ['label' => 'Landing', 'value' => $session['landingName'] ?? null],
            ['label' => 'Water', 'value' => $session['bodyOfWater'] ?? null],
            ['label' => 'Category', 'value' => $session['routeCategoryLabel'] ?? null],
            ['label' => 'Moving time', 'value' => $formatDuration($session['movingMinutes'] ?? null)],
        ], fn (array $item) => filled($item['value'])));

        $conditionFacts = array_values(array_filter([
            ['label' => 'Wind', 'value' => isset($session['windAvgMs']) && $session['windAvgMs'] !== null ? $units->formatWindMs((float) $session['windAvgMs']) : null],
            ['label' => 'Gust', 'value' => isset($session['windGustMs']) && $session['windGustMs'] !== null ? $units->formatWindMs((float) $session['windGustMs']) : null],
            ['label' => 'Beaufort', 'value' => isset($session['beaufort']) && $session['beaufort'] !== null ? 'F'.$session['beaufort'] : null],
            ['label' => 'Tide', 'value' => $session['tideState'] ?? null],
            ['label' => 'Current', 'value' => isset($session['currentKnots']) && $session['currentKnots'] !== null ? $units->formatCurrentKnots((float) $session['currentKnots']) : null],
            ['label' => 'Wave', 'value' => isset($session['waveHeightM']) && $session['waveHeightM'] !== null ? number_format((float) $session['waveHeightM'], 1).' m' : null],
            ['label' => 'Swell', 'value' => isset($session['swellHeightM']) && $session['swellHeightM'] !== null ? number_format((float) $session['swellHeightM'], 1).' m'.(isset($session['swellPeriodS']) && $session['swellPeriodS'] !== null ? ' @ '.number_format((float) $session['swellPeriodS'], 0).' s' : '') : null],
            ['label' => 'Air', 'value' => isset($session['airTempC']) && $session['airTempC'] !== null ? $units->formatTemperatureC((float) $session['airTempC']) : null],
            ['label' => 'Sea', 'value' => isset($session['seaTempC']) && $session['seaTempC'] !== null ? $units->formatTemperatureC((float) $session['seaTempC']) : null],
            ['label' => 'Visibility', 'value' => $session['visibilityCode'] ?? null],
        ], fn (array $item) => filled($item['value'])));

        $developmentFacts = array_values(array_filter([
            ['label' => 'Skills', 'value' => !empty($session['skills']) ? implode(', ', $session['skills']) : null],
            ['label' => 'Partners', 'value' => !empty($session['partners']) ? implode(', ', $session['partners']) : null],
            ['label' => 'Route tags', 'value' => !empty($session['routeTags']) ? implode(', ', $session['routeTags']) : null],
            ['label' => 'Rescue events', 'value' => sprintf('%d successful rolls, %d wet exits, %d tow rescues', (int) ($session['successfulRollsCount'] ?? 0), (int) ($session['wetExitsCount'] ?? 0), (int) ($session['towRescuesCount'] ?? 0))],
            ['label' => 'Scores', 'value' => collect([
                isset($session['confidenceScore']) && $session['confidenceScore'] !== null ? 'Confidence '.$session['confidenceScore'].'/5' : null,
                isset($session['fatigueScore']) && $session['fatigueScore'] !== null ? 'Fatigue '.$session['fatigueScore'].'/5' : null,
                isset($session['decisionScore']) && $session['decisionScore'] !== null ? 'Decision '.$session['decisionScore'].'/5' : null,
            ])->filter()->implode(' | ') ?: null],
            ['label' => 'Condition ratings', 'value' => collect($session['conditionRatings'] ?? [])->map(fn (array $rating) => ($rating['label'] ?? 'Rating').': '.($rating['value'] ?? '-'))->implode(' | ') ?: null],
            ['label' => 'Expedition days', 'value' => !empty($session['isExpedition']) ? ((string) ($session['expeditionDays'] ?? '-')) : null],
        ], fn (array $item) => filled($item['value'])));

        $noteCards = array_values(array_filter([
            ['label' => 'Observations', 'value' => $session['notesPublic'] ?? null, 'lead' => true],
            ['label' => 'What went well', 'value' => $session['whatWentWell'] ?? null, 'lead' => false],
            ['label' => 'Improve next', 'value' => $session['improveNext'] ?? null, 'lead' => false],
            ['label' => 'Route summary', 'value' => $session['routeSummary'] ?? null, 'lead' => false],
            ['label' => 'Weather summary', 'value' => $session['weatherSummary'] ?? null, 'lead' => false],
            ['label' => 'Session notes', 'value' => $session['notesPrivate'] ?? null, 'lead' => false],
            ['label' => 'Expedition notes', 'value' => $session['expeditionNotes'] ?? null, 'lead' => false],
        ], fn (array $item) => filled($item['value'])));

        $routeProfile = collect($session['routeProfile'] ?? [])
            ->filter(fn (array $point) => array_key_exists('x', $point) && array_key_exists('y', $point))
            ->values();
        $routePolyline = $routeProfile
            ->map(fn (array $point) => number_format((float) $point['x'], 1, '.', '').','.number_format((float) $point['y'], 1, '.', ''))
            ->implode(' ');
        $startPoint = $routeProfile->first();
        $endPoint = $routeProfile->last();
        $hasGeoTrack = $routeProfile->isNotEmpty()
            && $routeProfile->every(fn (array $point) => array_key_exists('lat', $point) && array_key_exists('lng', $point) && $point['lat'] !== null && $point['lng'] !== null);

        $attachmentFacts = array_values(array_filter([
            ['label' => 'Photo', 'value' => $session['photoName'] ?? null],
            ['label' => 'GPX', 'value' => $session['gpxName'] ?? null],
            ['label' => 'FIT', 'value' => $session['fitName'] ?? null],
        ], fn (array $item) => filled($item['value'])));

        $heroSummary = $session['notesPublic']
            ?? $session['routeSummary']
            ?? $session['weatherSummary']
            ?? 'A complete private session sheet for sharing the day without screenshots.';

        $routeLine = implode(' → ', array_values(array_filter([
            $session['launchName'] ?? null,
            $session['landingName'] ?? null,
        ])));

        $summaryHighlights = array_values(array_filter([
            [
                'label' => 'Route line',
                'value' => $routeLine ?: ($session['areaName'] ?? 'Area not named yet'),
            ],
            [
                'label' => 'Conditions snapshot',
                'value' => collect([
                    isset($session['beaufort']) && $session['beaufort'] !== null ? 'F'.$session['beaufort'] : null,
                    $session['tideState'] ?? null,
                    isset($session['waveHeightM']) && $session['waveHeightM'] !== null ? number_format((float) $session['waveHeightM'], 1).' m wave' : null,
                ])->filter()->implode(' · ') ?: 'Conditions not fully logged yet',
            ],
            [
                'label' => 'Files attached',
                'value' => $attachmentFacts !== []
                    ? collect($attachmentFacts)->pluck('label')->implode(' · ')
                    : 'No files attached',
            ],
        ], fn (array $item) => filled($item['value'])));

        $scoreMeters = array_values(array_filter([
            isset($session['confidenceScore']) && $session['confidenceScore'] !== null ? [
                'label' => 'Confidence',
                'value' => (int) $session['confidenceScore'],
                'percent' => max(0, min(100, ((int) $session['confidenceScore']) * 20)),
            ] : null,
            isset($session['fatigueScore']) && $session['fatigueScore'] !== null ? [
                'label' => 'Fatigue',
                'value' => (int) $session['fatigueScore'],
                'percent' => max(0, min(100, ((int) $session['fatigueScore']) * 20)),
            ] : null,
            isset($session['decisionScore']) && $session['decisionScore'] !== null ? [
                'label' => 'Decision',
                'value' => (int) $session['decisionScore'],
                'percent' => max(0, min(100, ((int) $session['decisionScore']) * 20)),
            ] : null,
        ]));
    @endphp
    <style>
        :root {
            color-scheme: light;
            --page-bg: #f6f7ff;
            --paper: rgba(255, 255, 255, 0.94);
            --panel: rgba(255, 255, 255, 0.86);
            --line: rgba(104, 112, 177, 0.18);
            --text: #232d63;
            --muted: #647099;
            --accent: #5c74ff;
            --accent-soft: rgba(92, 116, 255, 0.12);
            --warm: #ff8b5e;
            --shadow: 0 18px 48px rgba(57, 70, 137, 0.12);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:
                radial-gradient(circle at top, rgba(172, 234, 255, 0.28), transparent 36%),
                radial-gradient(circle at left, rgba(255, 218, 202, 0.26), transparent 28%),
                var(--page-bg);
            color: var(--text);
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        a {
            color: inherit;
        }

        .page {
            max-width: 1100px;
            margin: 0 auto;
            padding: 32px 24px 56px;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 18px;
        }

        .action-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .action-link,
        .action-button {
            border: 1px solid var(--line);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.88);
            color: var(--text);
            padding: 11px 18px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            box-shadow: 0 8px 20px rgba(53, 62, 111, 0.08);
        }

        .action-button {
            background: linear-gradient(135deg, rgba(92, 116, 255, 0.94), rgba(114, 150, 255, 0.94));
            color: #fff;
            border-color: transparent;
        }

        .sheet {
            position: relative;
            overflow: hidden;
            background: var(--paper);
            border: 1px solid rgba(117, 130, 211, 0.16);
            border-radius: 30px;
            padding: 28px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(18px);
        }

        .sheet::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(121, 217, 244, 0.14), transparent 32%),
                radial-gradient(circle at bottom left, rgba(255, 156, 99, 0.1), transparent 28%);
            pointer-events: none;
        }

        .brand-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
        }

        .hero-grid {
            position: relative;
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(250px, 0.75fr);
            gap: 18px;
            align-items: start;
        }

        .hero-copy {
            position: relative;
            z-index: 1;
        }

        .hero-summary {
            margin-top: 22px;
            padding: 18px 20px;
            border-radius: 24px;
            border: 1px solid rgba(117, 130, 211, 0.16);
            background:
                linear-gradient(135deg, rgba(92, 116, 255, 0.1), rgba(255, 255, 255, 0.84)),
                rgba(255, 255, 255, 0.84);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
        }

        .hero-summary-title {
            margin: 0 0 10px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.24em;
            color: var(--muted);
            text-transform: uppercase;
        }

        .hero-summary-body {
            margin: 0;
            font-size: 17px;
            line-height: 1.75;
            color: var(--text);
        }

        .kicker {
            margin: 0;
            color: var(--warm);
            letter-spacing: 0.3em;
            font-size: 12px;
            text-transform: uppercase;
        }

        .title {
            margin: 10px 0 8px;
            font-size: clamp(32px, 4.5vw, 56px);
            line-height: 0.96;
            letter-spacing: -0.04em;
        }

        .subtitle,
        .meta-text {
            margin: 0;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.7;
        }

        .profile-card {
            min-width: 240px;
            max-width: 320px;
            padding: 16px 18px;
            border: 1px solid var(--line);
            border-radius: 22px;
            background: var(--panel);
            position: relative;
            z-index: 1;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
        }

        .profile-card strong {
            display: block;
            font-size: 18px;
            line-height: 1.2;
        }

        .profile-meta-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-top: 14px;
        }

        .profile-meta {
            padding: 10px 12px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.74);
            border: 1px solid rgba(117, 130, 211, 0.12);
        }

        .profile-meta-label {
            display: block;
            margin-bottom: 4px;
            color: var(--muted);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .profile-meta-value {
            font-size: 14px;
            line-height: 1.35;
            font-weight: 700;
        }

        .chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.72);
            color: var(--muted);
            font-size: 13px;
            font-weight: 600;
        }

        .grid {
            display: grid;
            gap: 18px;
            margin-top: 22px;
            position: relative;
            z-index: 1;
        }

        .stats-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .two-col {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .summary-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .card {
            border: 1px solid var(--line);
            border-radius: 24px;
            padding: 20px;
            background: var(--panel);
            break-inside: avoid;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.68);
        }

        .card h2,
        .card h3 {
            margin: 0;
            font-size: 27px;
            line-height: 1;
            letter-spacing: -0.04em;
        }

        .stat-card {
            background: linear-gradient(135deg, rgba(92, 116, 255, 0.09), rgba(255, 255, 255, 0.92));
        }

        .stats-grid .stat-card:nth-child(2) {
            background: linear-gradient(135deg, rgba(102, 208, 208, 0.14), rgba(255, 255, 255, 0.92));
        }

        .stats-grid .stat-card:nth-child(3) {
            background: linear-gradient(135deg, rgba(255, 202, 143, 0.16), rgba(255, 255, 255, 0.92));
        }

        .stats-grid .stat-card:nth-child(4) {
            background: linear-gradient(135deg, rgba(164, 162, 255, 0.16), rgba(255, 255, 255, 0.92));
        }

        .stat-card .value {
            margin-top: 16px;
            font-size: 34px;
            line-height: 1;
            font-weight: 700;
            letter-spacing: -0.04em;
        }

        .summary-card {
            padding: 16px 18px;
            border-radius: 22px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.9), rgba(246, 248, 255, 0.92));
        }

        .summary-value {
            margin-top: 12px;
            font-size: 18px;
            line-height: 1.45;
            font-weight: 700;
            color: var(--text);
        }

        .fact-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px 16px;
            margin-top: 18px;
        }

        .fact {
            padding: 12px 14px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.76);
            border: 1px solid rgba(117, 130, 211, 0.12);
        }

        .fact-label {
            display: block;
            margin-bottom: 6px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.2em;
            text-transform: uppercase;
        }

        .fact-value {
            font-size: 16px;
            line-height: 1.45;
            font-weight: 600;
        }

        .route-preview {
            margin-top: 18px;
            border-radius: 22px;
            overflow: hidden;
            border: 1px solid var(--line);
            background:
                linear-gradient(180deg, rgba(229, 240, 255, 0.92), rgba(249, 250, 255, 0.99));
        }

        .route-preview svg {
            display: block;
            width: 100%;
            height: auto;
        }

        .route-caption {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 10px;
            padding: 14px 16px 16px;
            color: var(--muted);
            font-size: 13px;
        }

        .photo {
            width: 100%;
            border-radius: 24px;
            object-fit: cover;
            max-height: 460px;
            display: block;
        }

        .score-meters {
            display: grid;
            gap: 12px;
            margin-top: 18px;
        }

        .score-meter {
            padding: 14px 15px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.76);
            border: 1px solid rgba(117, 130, 211, 0.12);
        }

        .score-meter-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: baseline;
            margin-bottom: 10px;
        }

        .score-meter-title {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .score-meter-value {
            font-size: 18px;
            font-weight: 800;
            color: var(--text);
        }

        .score-meter-track {
            position: relative;
            height: 10px;
            border-radius: 999px;
            background: rgba(92, 116, 255, 0.12);
            overflow: hidden;
        }

        .score-meter-fill {
            position: absolute;
            inset: 0 auto 0 0;
            border-radius: inherit;
            background: linear-gradient(90deg, #5c74ff, #80b7ff);
        }

        .notes-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .note-card {
            min-height: 170px;
        }

        .note-card--lead {
            grid-column: span 2;
            min-height: 210px;
            background: linear-gradient(135deg, rgba(92, 116, 255, 0.08), rgba(255, 255, 255, 0.94));
        }

        .note-body {
            margin: 16px 0 0;
            color: var(--text);
            font-size: 16px;
            line-height: 1.75;
            white-space: pre-wrap;
        }

        .note-card--lead .note-body {
            font-size: 18px;
            line-height: 1.8;
        }

        .footer {
            margin-top: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 14px;
            color: var(--muted);
            font-size: 13px;
        }

        @media (max-width: 900px) {
            .stats-grid,
            .summary-grid,
            .two-col,
            .notes-grid {
                grid-template-columns: 1fr;
            }

            .note-card--lead {
                grid-column: span 1;
            }

            .fact-list {
                grid-template-columns: 1fr;
            }

            .hero-grid {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            @page {
                size: A4;
                margin: 12mm;
            }

            body {
                background: #fff;
            }

            .page {
                max-width: none;
                margin: 0;
                padding: 0;
            }

            .no-print {
                display: none !important;
            }

            .sheet {
                border: 0;
                border-radius: 0;
                box-shadow: none;
                padding: 0;
                background: #fff;
            }

            .sheet::before {
                display: none;
            }

            .card,
            .profile-card,
            .fact,
            .route-preview,
            .summary-card,
            .hero-summary,
            .score-meter,
            .profile-meta {
                box-shadow: none;
                background: #fff;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="actions no-print">
            <div class="action-group">
                <a class="action-link" href="{{ route('sessions.show', $session['id']) }}">Back to session</a>
                <a class="action-link" href="{{ route('sessions.index') }}">Library</a>
            </div>
            <div class="action-group">
                <button class="action-button" type="button" onclick="window.print()">Print / Save PDF</button>
            </div>
        </div>

        <main class="sheet">
            <section>
                <div class="hero-grid">
                    <div class="hero-copy">
                        <p class="kicker">Sea kayak logbook</p>
                        <h1 class="title">{{ $session['title'] }}</h1>
                        <p class="subtitle">
                            {{ implode(' | ', array_values(array_filter([$session['date'] ?? null, $session['launchName'] ?? $session['areaName'] ?? null, filled($session['startTimeLocal'] ?? null) ? ($session['startTimeLocal'].' '.$session['timezone']) : null]))) ?: 'Shared session summary' }}
                        </p>

                        <div class="hero-summary">
                            <p class="hero-summary-title">Session snapshot</p>
                            <p class="hero-summary-body">{{ $heroSummary }}</p>
                        </div>
                    </div>

                    <aside class="profile-card">
                        <p class="kicker">Shared by</p>
                        <strong>{{ $profile['name'] ?? 'Paddler' }}</strong>
                        <p class="meta-text" style="margin-top: 8px;">
                            {{ $profile['homeWater'] ?? 'Sea kayaking journal' }}
                        </p>
                        <p class="meta-text" style="margin-top: 10px;">
                            Print this page or save it as PDF to send a complete session summary without screenshots.
                        </p>

                        <div class="profile-meta-grid">
                            <div class="profile-meta">
                                <span class="profile-meta-label">Session type</span>
                                <div class="profile-meta-value">{{ !empty($session['isExpedition']) ? 'Expedition' : 'Day session' }}</div>
                            </div>
                            <div class="profile-meta">
                                <span class="profile-meta-label">Export</span>
                                <div class="profile-meta-value">Private PDF share</div>
                            </div>
                        </div>
                    </aside>
                </div>

                @if ($chipValues !== [])
                    <div class="chip-row">
                        @foreach ($chipValues as $chip)
                            <span class="chip">{{ $chip }}</span>
                        @endforeach
                    </div>
                @endif

                @if ($summaryHighlights !== [])
                    <div class="grid summary-grid">
                        @foreach ($summaryHighlights as $highlight)
                            <article class="card summary-card">
                                <p class="kicker">{{ $highlight['label'] }}</p>
                                <div class="summary-value">{{ $highlight['value'] }}</div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="grid stats-grid">
                @foreach ($keyStats as $stat)
                    <article class="card stat-card">
                        <p class="kicker">{{ $stat['label'] }}</p>
                        <div class="value">{{ $stat['value'] }}</div>
                    </article>
                @endforeach
            </section>

            <section class="grid two-col">
                <article class="card">
                    <p class="kicker">Journey</p>
                    <h2>Route overview</h2>
                    <div class="fact-list">
                        @foreach ($journeyFacts as $fact)
                            <div class="fact">
                                <span class="fact-label">{{ $fact['label'] }}</span>
                                <div class="fact-value">{{ $fact['value'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="card">
                    <p class="kicker">Conditions</p>
                    <h2>Sea and weather</h2>
                    <div class="fact-list">
                        @forelse ($conditionFacts as $fact)
                            <div class="fact">
                                <span class="fact-label">{{ $fact['label'] }}</span>
                                <div class="fact-value">{{ $fact['value'] }}</div>
                            </div>
                        @empty
                            <div class="fact" style="grid-column: 1 / -1;">
                                <span class="fact-label">Status</span>
                                <div class="fact-value">No environmental details were logged for this session yet.</div>
                            </div>
                        @endforelse
                    </div>
                </article>
            </section>

            @if ($routePolyline !== '')
                <section class="grid">
                    <article class="card">
                        <p class="kicker">{{ $hasGeoTrack ? 'Track' : 'Profile' }}</p>
                        <h2>{{ $hasGeoTrack ? 'Route trace' : 'Movement profile' }}</h2>

                        <div class="route-preview">
                            <svg viewBox="0 0 320 150" role="img" aria-label="{{ $hasGeoTrack ? 'Route trace preview' : 'Movement profile preview' }}">
                                <defs>
                                    <pattern id="share-grid" width="24" height="24" patternUnits="userSpaceOnUse">
                                        <path d="M 24 0 L 0 0 0 24" fill="none" stroke="rgba(92,116,255,0.12)" stroke-width="1" />
                                    </pattern>
                                </defs>
                                <rect width="320" height="150" fill="url(#share-grid)" />
                                <polyline points="{{ $routePolyline }}" fill="none" stroke="#5c74ff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
                                @if ($startPoint)
                                    <circle cx="{{ $startPoint['x'] }}" cy="{{ $startPoint['y'] }}" r="7" fill="#10b981" />
                                @endif
                                @if ($endPoint)
                                    <circle cx="{{ $endPoint['x'] }}" cy="{{ $endPoint['y'] }}" r="7" fill="#f97316" />
                                @endif
                            </svg>

                            <div class="route-caption">
                                <span>{{ $hasGeoTrack ? 'Green marks the start and orange marks the landing or finish.' : 'Preview built from the saved session profile.' }}</span>
                                <span>{{ count($session['routeProfile'] ?? []) }} sampled points</span>
                            </div>
                        </div>
                    </article>
                </section>
            @endif

            @if (!empty($session['photoUrl']))
                <section class="grid">
                    <article class="card">
                        <p class="kicker">Photo</p>
                        <h2>Session image</h2>
                        <div style="margin-top: 18px;">
                            <img class="photo" src="{{ $session['photoUrl'] }}" alt="{{ $session['photoName'] ?? $session['title'] }}">
                        </div>
                    </article>
                </section>
            @endif

            <section class="grid two-col">
                <article class="card">
                    <p class="kicker">Development</p>
                    <h2>Skills and outcomes</h2>
                    @if ($scoreMeters !== [])
                        <div class="score-meters">
                            @foreach ($scoreMeters as $score)
                                <div class="score-meter">
                                    <div class="score-meter-head">
                                        <span class="score-meter-title">{{ $score['label'] }}</span>
                                        <span class="score-meter-value">{{ $score['value'] }}/5</span>
                                    </div>
                                    <div class="score-meter-track">
                                        <div class="score-meter-fill" style="width: {{ $score['percent'] }}%;"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <div class="fact-list">
                        @forelse ($developmentFacts as $fact)
                            <div class="fact">
                                <span class="fact-label">{{ $fact['label'] }}</span>
                                <div class="fact-value">{{ $fact['value'] }}</div>
                            </div>
                        @empty
                            <div class="fact" style="grid-column: 1 / -1;">
                                <span class="fact-label">Status</span>
                                <div class="fact-value">No development details were logged for this session yet.</div>
                            </div>
                        @endforelse
                    </div>
                </article>

                <article class="card">
                    <p class="kicker">Attachments</p>
                    <h2>Files on record</h2>
                    <div class="fact-list">
                        @forelse ($attachmentFacts as $fact)
                            <div class="fact">
                                <span class="fact-label">{{ $fact['label'] }}</span>
                                <div class="fact-value">{{ $fact['value'] }}</div>
                            </div>
                        @empty
                            <div class="fact" style="grid-column: 1 / -1;">
                                <span class="fact-label">Status</span>
                                <div class="fact-value">No files were attached to this session.</div>
                            </div>
                        @endforelse
                    </div>
                </article>
            </section>

            @if ($noteCards !== [])
                <section class="grid notes-grid">
                    @foreach ($noteCards as $note)
                        <article class="card note-card {{ !empty($note['lead']) ? 'note-card--lead' : '' }}">
                            <p class="kicker">{{ $note['label'] }}</p>
                            <div class="note-body">{{ $note['value'] }}</div>
                        </article>
                    @endforeach
                </section>
            @endif

            <footer class="footer">
                <span>Shared from {{ $profile['name'] ?? 'Sea Kayak Logbook' }}</span>
                <span>Generated {{ now()->format('d M Y H:i') }} {{ config('app.timezone') }}</span>
            </footer>
        </main>
    </div>
</body>
</html>
