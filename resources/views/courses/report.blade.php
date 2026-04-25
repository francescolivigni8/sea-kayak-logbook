<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $report['profile']['paddlerName'] }} | Course application report</title>
    @php
        $units = \App\Support\UnitFormat::fromPreferences($unitPreferences ?? []);
        $formatDistance = fn ($value, int $digits = 1) => $units->formatDistanceKm($value !== null ? (float) $value : null, $digits);
        $formatSpeed = fn ($value, int $digits = 1) => $units->formatSpeedKnots($value !== null ? (float) $value : null, $digits);
        $formatTemperature = fn ($value, int $digits = 1) => $units->formatTemperatureC($value !== null ? (float) $value : null, $digits);

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

        $heroFacts = array_values(array_filter([
            $report['profile']['homeWater'] ?? null,
            $report['profile']['kayakClub'] ?? null,
            ($report['headline']['trackSessions'] ?? 0) > 0 ? (($report['headline']['trackSessions'] ?? 0).' tracked sessions') : null,
            ($report['expeditionSummary']['tripCount'] ?? 0) > 0 ? (($report['expeditionSummary']['tripCount'] ?? 0).' expedition sessions') : null,
        ], fn ($value) => filled($value)));

        $summaryCards = [
            [
                'label' => 'Total sessions',
                'value' => number_format((int) ($report['headline']['sessionCount'] ?? 0)),
                'detail' => 'Complete journal appendix included',
            ],
            [
                'label' => 'Distance paddled',
                'value' => $formatDistance($report['headline']['distanceKm'] ?? null),
                'detail' => number_format((float) ($report['headline']['durationHours'] ?? 0), 1).' h total duration',
            ],
            [
                'label' => 'Longest paddle',
                'value' => $formatDistance($report['headline']['longestDistanceKm'] ?? null),
                'detail' => ($report['headline']['averageSpeedKnots'] !== null ? $formatSpeed($report['headline']['averageSpeedKnots']).' avg pace' : 'Average pace still building'),
            ],
            [
                'label' => 'Expedition time',
                'value' => (int) ($report['expeditionSummary']['daysOut'] ?? 0).' days',
                'detail' => (int) ($report['expeditionSummary']['tripCount'] ?? 0).' expedition sessions',
            ],
        ];

        $profileFacts = array_values(array_filter([
            ['label' => 'Candidate', 'value' => $report['profile']['paddlerName'] ?? $report['profile']['name']],
            ['label' => 'Home water', 'value' => $report['profile']['homeWater'] ?? null],
            ['label' => 'Club', 'value' => $report['profile']['kayakClub'] ?? null],
            ['label' => 'Timezone', 'value' => $report['profile']['timezone'] ?? null],
            ['label' => 'Kayaks', 'value' => !empty($report['profile']['kayaksOwned']) ? implode(', ', $report['profile']['kayaksOwned']) : null],
            ['label' => 'Paddles', 'value' => !empty($report['profile']['paddlesOwned']) ? implode(', ', $report['profile']['paddlesOwned']) : null],
        ], fn (array $item) => filled($item['value'])));

        $experienceFacts = [
            ['label' => 'Paddled months', 'value' => (int) ($report['headline']['paddledMonths'] ?? 0)],
            ['label' => 'Tracked sessions', 'value' => (int) ($report['headline']['trackSessions'] ?? 0)],
            ['label' => 'Observation entries', 'value' => (int) ($report['observationCount'] ?? 0)],
            ['label' => 'Average distance', 'value' => $formatDistance($report['headline']['averageDistanceKm'] ?? null)],
        ];

        $averageBeaufort = $report['seaState']['averageBeaufort'] ?? null;
        $temperatureAverages = $report['seaState']['temperatureAverages'] ?? ['air' => null, 'sea' => null];
        $routeMixMax = max(array_merge([1], collect($report['routeMix'] ?? [])->pluck('distanceKm')->map(fn ($value) => (float) $value)->all()));
        $yearSnapshots = $report['yearSnapshots'] ?? [];
        $monthlyDistance = $report['monthlyDistance'] ?? [];
        $monthlyDistanceMax = max(array_merge([1], collect($monthlyDistance)->pluck('distanceKm')->map(fn ($value) => (float) $value)->all()));
        $beaufortBands = collect($report['seaState']['beaufortBands'] ?? [])->filter(fn (array $band) => ($band['count'] ?? 0) > 0)->values();
        $beaufortMax = max(array_merge([1], $beaufortBands->pluck('count')->map(fn ($value) => (int) $value)->all()));
        $tideStates = collect($report['seaState']['tideStates'] ?? [])->filter(fn (array $state) => ($state['count'] ?? 0) > 0)->values();
        $rescueTotals = $report['seaState']['rescueTotals'] ?? [];
        $recentSessions = $report['recentSessions'] ?? [];
        $sessionLog = $report['sessionLog'] ?? [];
    @endphp
    <style>
        :root {
            color-scheme: light;
            --page-bg: #f4f6ff;
            --paper: #ffffff;
            --surface: #f8f9ff;
            --surface-strong: #eef2ff;
            --line: rgba(80, 92, 170, 0.18);
            --line-strong: rgba(80, 92, 170, 0.28);
            --text: #232d63;
            --muted: #68729a;
            --accent: #5c74ff;
            --accent-soft: rgba(92, 116, 255, 0.12);
            --sea: #77d8ea;
            --warm: #ff8b5e;
            --success: #20b486;
            --danger: #f97362;
            --shadow: 0 20px 52px rgba(40, 51, 112, 0.12);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top, rgba(119, 216, 234, 0.24), transparent 30%),
                radial-gradient(circle at 20% 0%, rgba(255, 155, 104, 0.16), transparent 18%),
                var(--page-bg);
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        img {
            max-width: 100%;
            display: block;
        }

        .page {
            max-width: 1180px;
            margin: 0 auto;
            padding: 28px 22px 64px;
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
            background: rgba(255, 255, 255, 0.9);
            color: var(--text);
            padding: 11px 18px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            box-shadow: 0 10px 24px rgba(53, 62, 111, 0.08);
        }

        .action-button {
            background: linear-gradient(135deg, rgba(92, 116, 255, 0.94), rgba(114, 150, 255, 0.96));
            color: #fff;
            border-color: transparent;
        }

        .sheet {
            position: relative;
            overflow: hidden;
            padding: 28px;
            border-radius: 34px;
            border: 1px solid rgba(110, 123, 204, 0.18);
            background: rgba(255, 255, 255, 0.94);
            box-shadow: var(--shadow);
            backdrop-filter: blur(18px);
        }

        .sheet::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(119, 216, 234, 0.14), transparent 34%),
                radial-gradient(circle at bottom left, rgba(255, 139, 94, 0.12), transparent 28%);
            pointer-events: none;
        }

        .hero {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(280px, 0.85fr);
            gap: 22px;
            align-items: start;
        }

        .hero-brand {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .hero-logo {
            width: 86px;
            height: 86px;
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(103, 114, 255, 0.18);
            box-shadow: 0 18px 36px rgba(37, 43, 82, 0.14);
            flex-shrink: 0;
        }

        .kicker {
            margin: 0;
            color: var(--warm);
            letter-spacing: 0.28em;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .hero-title {
            margin: 10px 0 6px;
            font-size: clamp(34px, 4.5vw, 56px);
            line-height: 0.94;
            letter-spacing: -0.05em;
        }

        .hero-subtitle {
            margin: 0;
            color: var(--muted);
            font-size: 16px;
            line-height: 1.7;
            max-width: 680px;
        }

        .hero-facts {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.74);
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
        }

        .hero-card {
            position: relative;
            z-index: 1;
            border: 1px solid var(--line);
            border-radius: 28px;
            padding: 20px;
            background: linear-gradient(135deg, rgba(92, 116, 255, 0.1), rgba(255, 255, 255, 0.88));
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.78);
        }

        .hero-card-value {
            margin-top: 16px;
            font-size: 40px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.05em;
        }

        .hero-card-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-top: 16px;
        }

        .hero-card-item {
            padding: 10px 12px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.72);
            border: 1px solid rgba(110, 123, 204, 0.12);
        }

        .hero-card-item-label {
            display: block;
            margin-bottom: 4px;
            color: var(--muted);
            font-size: 10px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            font-weight: 700;
        }

        .hero-card-item-value {
            font-size: 15px;
            font-weight: 700;
            line-height: 1.4;
        }

        .grid {
            position: relative;
            z-index: 1;
            display: grid;
            gap: 18px;
            margin-top: 22px;
        }

        .summary-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .two-col {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .three-col {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .card {
            border: 1px solid var(--line);
            border-radius: 26px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.82);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.76);
            break-inside: avoid;
        }

        .summary-card:nth-child(1) {
            background: linear-gradient(135deg, rgba(92, 116, 255, 0.1), rgba(255, 255, 255, 0.9));
        }

        .summary-card:nth-child(2) {
            background: linear-gradient(135deg, rgba(119, 216, 234, 0.16), rgba(255, 255, 255, 0.9));
        }

        .summary-card:nth-child(3) {
            background: linear-gradient(135deg, rgba(255, 205, 143, 0.18), rgba(255, 255, 255, 0.9));
        }

        .summary-card:nth-child(4) {
            background: linear-gradient(135deg, rgba(160, 146, 255, 0.16), rgba(255, 255, 255, 0.9));
        }

        .card-title {
            margin: 10px 0 0;
            font-size: 28px;
            line-height: 0.98;
            letter-spacing: -0.04em;
        }

        .summary-value {
            margin-top: 16px;
            font-size: 34px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.05em;
        }

        .summary-detail,
        .copy {
            margin: 10px 0 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.7;
        }

        .fact-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-top: 18px;
        }

        .fact {
            padding: 12px 14px;
            border-radius: 18px;
            border: 1px solid rgba(110, 123, 204, 0.12);
            background: rgba(255, 255, 255, 0.74);
        }

        .fact-label {
            display: block;
            margin-bottom: 5px;
            color: var(--muted);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .fact-value {
            font-size: 16px;
            font-weight: 700;
            line-height: 1.45;
        }

        .bar-list {
            display: grid;
            gap: 12px;
            margin-top: 18px;
        }

        .bar-row {
            display: grid;
            grid-template-columns: 130px minmax(0, 1fr) auto;
            gap: 12px;
            align-items: center;
        }

        .bar-label,
        .bar-value {
            font-size: 14px;
            font-weight: 700;
        }

        .bar-label {
            color: var(--text);
        }

        .bar-value {
            color: var(--muted);
            text-align: right;
        }

        .bar-track {
            position: relative;
            height: 10px;
            border-radius: 999px;
            background: rgba(92, 116, 255, 0.12);
            overflow: hidden;
        }

        .bar-fill {
            position: absolute;
            inset: 0 auto 0 0;
            border-radius: inherit;
            background: linear-gradient(90deg, #5c74ff, #8ac4ff);
        }

        .mini-grid {
            display: grid;
            gap: 12px;
            margin-top: 18px;
        }

        .mini-grid.three {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .mini-card {
            padding: 14px 15px;
            border-radius: 18px;
            border: 1px solid rgba(110, 123, 204, 0.12);
            background: rgba(255, 255, 255, 0.72);
        }

        .mini-card-value {
            margin-top: 10px;
            font-size: 24px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .skill-cloud,
        .folder-cloud {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }

        .tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(92, 116, 255, 0.08);
            border: 1px solid rgba(110, 123, 204, 0.14);
            color: var(--text);
            font-size: 13px;
            font-weight: 700;
        }

        .tag-count {
            color: var(--muted);
            font-weight: 800;
        }

        .evidence-grid {
            display: grid;
            gap: 14px;
            margin-top: 18px;
        }

        .evidence-card {
            border-radius: 22px;
            padding: 18px;
            border: 1px solid rgba(110, 123, 204, 0.14);
            background: linear-gradient(135deg, rgba(92, 116, 255, 0.06), rgba(255, 255, 255, 0.88));
            break-inside: avoid;
        }

        .evidence-head {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 12px;
        }

        .evidence-title {
            margin: 6px 0 0;
            font-size: 22px;
            line-height: 1.05;
            letter-spacing: -0.04em;
        }

        .evidence-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }

        .evidence-body {
            margin-top: 14px;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.75;
        }

        .note-list {
            display: grid;
            gap: 12px;
            margin-top: 18px;
        }

        .note-card {
            padding: 16px 18px;
            border-radius: 20px;
            background: linear-gradient(135deg, rgba(119, 216, 234, 0.08), rgba(255, 255, 255, 0.92));
            border: 1px solid rgba(110, 123, 204, 0.12);
        }

        .note-source {
            display: inline-flex;
            align-items: center;
            margin-top: 10px;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.76);
            border: 1px solid rgba(110, 123, 204, 0.12);
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
        }

        .appendix {
            page-break-before: always;
        }

        .log-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 16px;
        }

        .log-table-wrap {
            margin-top: 18px;
            border: 1px solid var(--line);
            border-radius: 24px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.92);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: rgba(92, 116, 255, 0.08);
        }

        th,
        td {
            padding: 11px 12px;
            text-align: left;
            border-bottom: 1px solid rgba(110, 123, 204, 0.12);
            vertical-align: top;
        }

        th {
            color: var(--muted);
            font-size: 11px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        td {
            font-size: 13px;
            line-height: 1.55;
            color: var(--text);
        }

        tbody tr:nth-child(even) {
            background: rgba(244, 246, 255, 0.56);
        }

        .table-muted {
            color: var(--muted);
        }

        .footer {
            position: relative;
            z-index: 1;
            margin-top: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 14px;
            color: var(--muted);
            font-size: 13px;
        }

        @media (max-width: 960px) {
            .hero,
            .summary-grid,
            .two-col,
            .three-col,
            .mini-grid.three {
                grid-template-columns: 1fr;
            }

            .fact-grid {
                grid-template-columns: 1fr;
            }

            .bar-row {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 10mm;
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
                padding: 0;
                box-shadow: none;
                background: #fff;
            }

            .sheet::before {
                display: none;
            }

            .card,
            .hero-card,
            .hero-card-item,
            .fact,
            .mini-card,
            .evidence-card,
            .note-card,
            .log-table-wrap,
            .tag,
            .chip {
                box-shadow: none;
            }

            thead {
                display: table-header-group;
            }

            tr,
            .evidence-card,
            .note-card,
            .card {
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="actions no-print">
            <div class="action-group">
                <a class="action-link" href="{{ route('profile.edit') }}">Back to account</a>
                <a class="action-link" href="{{ route('dashboard') }}">Dashboard</a>
            </div>
            <div class="action-group">
                <button class="action-button" type="button" onclick="window.print()">Print / Save PDF</button>
            </div>
        </div>

        <main class="sheet">
            <section class="hero">
                <div>
                    <div class="hero-brand">
                        <div class="hero-logo">
                            <img src="/brand/ykj-logo-clean.png" alt="Your Kayaking Journal logo">
                        </div>
                        <div>
                            <p class="kicker">Course application</p>
                            <h1 class="hero-title">{{ $report['profile']['paddlerName'] }}</h1>
                            <p class="hero-subtitle">
                                {{ $report['purpose'] }} report generated from the full paddling journal. This document is designed to support applications for advanced sea kayak training by combining headline experience, environmental exposure, reflection, and the full log appendix.
                            </p>
                        </div>
                    </div>

                    @if ($heroFacts !== [])
                        <div class="hero-facts">
                            @foreach ($heroFacts as $fact)
                                <span class="chip">{{ $fact }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <aside class="hero-card">
                    <p class="kicker">Report generated</p>
                    <div class="hero-card-value">{{ $report['generatedAt'] }}</div>
                    <div class="hero-card-grid">
                        <div class="hero-card-item">
                            <span class="hero-card-item-label">Distance</span>
                            <div class="hero-card-item-value">{{ $formatDistance($report['headline']['distanceKm'] ?? null) }}</div>
                        </div>
                        <div class="hero-card-item">
                            <span class="hero-card-item-label">Hours</span>
                            <div class="hero-card-item-value">{{ number_format((float) ($report['headline']['durationHours'] ?? 0), 1) }} h</div>
                        </div>
                        <div class="hero-card-item">
                            <span class="hero-card-item-label">Sessions</span>
                            <div class="hero-card-item-value">{{ (int) ($report['headline']['sessionCount'] ?? 0) }}</div>
                        </div>
                        <div class="hero-card-item">
                            <span class="hero-card-item-label">Observations</span>
                            <div class="hero-card-item-value">{{ (int) ($report['observationCount'] ?? 0) }}</div>
                        </div>
                    </div>
                </aside>
            </section>

            <section class="grid summary-grid">
                @foreach ($summaryCards as $card)
                    <article class="card summary-card">
                        <p class="kicker">{{ $card['label'] }}</p>
                        <div class="summary-value">{{ $card['value'] }}</div>
                        <p class="summary-detail">{{ $card['detail'] }}</p>
                    </article>
                @endforeach
            </section>

            <section class="grid two-col">
                <article class="card">
                    <p class="kicker">Candidate</p>
                    <h2 class="card-title">Profile and equipment</h2>
                    <div class="fact-grid">
                        @foreach ($profileFacts as $fact)
                            <div class="fact">
                                <span class="fact-label">{{ $fact['label'] }}</span>
                                <div class="fact-value">{{ $fact['value'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="card">
                    <p class="kicker">Experience</p>
                    <h2 class="card-title">At-a-glance overview</h2>
                    <div class="mini-grid three">
                        @foreach ($experienceFacts as $fact)
                            <div class="mini-card">
                                <span class="fact-label">{{ $fact['label'] }}</span>
                                <div class="mini-card-value">{{ $fact['value'] }}</div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mini-grid three">
                        <div class="mini-card">
                            <span class="fact-label">Average Beaufort</span>
                            <div class="mini-card-value">{{ $averageBeaufort !== null ? 'F'.number_format((float) $averageBeaufort, 1) : '-' }}</div>
                        </div>
                        <div class="mini-card">
                            <span class="fact-label">Average air</span>
                            <div class="mini-card-value">{{ $formatTemperature($temperatureAverages['air'] ?? null) }}</div>
                        </div>
                        <div class="mini-card">
                            <span class="fact-label">Average sea</span>
                            <div class="mini-card-value">{{ $formatTemperature($temperatureAverages['sea'] ?? null) }}</div>
                        </div>
                    </div>
                </article>
            </section>

            <section class="grid two-col">
                <article class="card">
                    <p class="kicker">Route mix</p>
                    <h2 class="card-title">Session categories by distance</h2>
                    <div class="bar-list">
                        @foreach ($report['routeMix'] as $item)
                            <div class="bar-row">
                                <div class="bar-label">{{ $item['label'] }}</div>
                                <div class="bar-track">
                                    <div class="bar-fill" style="width: {{ round((((float) $item['distanceKm']) / $routeMixMax) * 100, 1) }}%;"></div>
                                </div>
                                <div class="bar-value">{{ $formatDistance($item['distanceKm']) }}</div>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="card">
                    <p class="kicker">Distance windows</p>
                    <h2 class="card-title">All time, year, and rolling view</h2>
                    <div class="mini-grid">
                        @foreach ($yearSnapshots as $snapshot)
                            <div class="mini-card">
                                <span class="fact-label">{{ $snapshot['label'] }}</span>
                                <div class="mini-card-value">{{ $formatDistance($snapshot['value'] ?? null) }}</div>
                                <p class="summary-detail">{{ $snapshot['detail'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </article>
            </section>

            <section class="grid two-col">
                <article class="card">
                    <p class="kicker">Consistency</p>
                    <h2 class="card-title">Distance by month</h2>
                    <div class="bar-list">
                        @foreach ($monthlyDistance as $month)
                            <div class="bar-row">
                                <div class="bar-label">{{ $month['label'] }}</div>
                                <div class="bar-track">
                                    <div class="bar-fill" style="width: {{ round((((float) $month['distanceKm']) / $monthlyDistanceMax) * 100, 1) }}%;"></div>
                                </div>
                                <div class="bar-value">{{ $formatDistance($month['distanceKm']) }}</div>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="card">
                    <p class="kicker">Recent form</p>
                    <h2 class="card-title">Recent sessions snapshot</h2>
                    <div class="note-list">
                        @forelse ($recentSessions as $session)
                            <article class="note-card">
                                <p class="kicker">{{ $session['date'] ?? 'Session' }}</p>
                                <p style="margin: 10px 0 0; font-size: 20px; font-weight: 800; line-height: 1.1;">{{ $session['title'] }}</p>
                                <p class="copy">{{ $session['routeCategoryLabel'] }} · {{ $formatDistance($session['distanceKm']) }} · {{ $formatDuration($session['durationMinutes']) }}</p>
                                <div class="hero-facts" style="margin-top: 12px;">
                                    @if ($session['launchName'])
                                        <span class="chip">{{ $session['launchName'] }}</span>
                                    @endif
                                    @if ($session['beaufort'] !== null)
                                        <span class="chip">F{{ $session['beaufort'] }}</span>
                                    @endif
                                    @if ($session['hasTrack'])
                                        <span class="chip">Track attached</span>
                                    @endif
                                    @if ($session['isExpedition'])
                                        <span class="chip">Expedition</span>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <p class="copy">No recent sessions available yet.</p>
                        @endforelse
                    </div>
                </article>
            </section>

            <section class="grid three-col">
                <article class="card">
                    <p class="kicker">Wind</p>
                    <h2 class="card-title">Beaufort exposure</h2>
                    <div class="bar-list">
                        @forelse ($beaufortBands as $band)
                            <div class="bar-row">
                                <div class="bar-label">{{ $band['label'] }}</div>
                                <div class="bar-track">
                                    <div class="bar-fill" style="width: {{ round((((int) $band['count']) / $beaufortMax) * 100, 1) }}%;"></div>
                                </div>
                                <div class="bar-value">{{ (int) $band['count'] }}</div>
                            </div>
                        @empty
                            <p class="copy">No Beaufort values logged yet.</p>
                        @endforelse
                    </div>
                </article>

                <article class="card">
                    <p class="kicker">Tide</p>
                    <h2 class="card-title">Tide states logged</h2>
                    <div class="skill-cloud">
                        @forelse ($tideStates as $state)
                            <span class="tag">
                                {{ $state['label'] }}
                                <span class="tag-count">{{ $state['count'] }}</span>
                            </span>
                        @empty
                            <p class="copy">No tide states logged yet.</p>
                        @endforelse
                    </div>
                </article>

                <article class="card">
                    <p class="kicker">Rescue</p>
                    <h2 class="card-title">Development totals</h2>
                    <div class="mini-grid">
                        @foreach ($rescueTotals as $item)
                            <div class="mini-card">
                                <span class="fact-label">{{ $item['label'] }}</span>
                                <div class="mini-card-value">{{ (int) ($item['count'] ?? 0) }}</div>
                            </div>
                        @endforeach
                    </div>
                </article>
            </section>

            <section class="grid two-col">
                <article class="card">
                    <p class="kicker">Skills</p>
                    <h2 class="card-title">What gets logged most</h2>
                    <div class="skill-cloud">
                        @forelse ($report['skillsSummary'] as $skill)
                            <span class="tag">
                                {{ $skill['label'] }}
                                <span class="tag-count">{{ $skill['count'] }}</span>
                            </span>
                        @empty
                            <p class="copy">No skill tags logged yet.</p>
                        @endforelse
                    </div>
                </article>

                <article class="card">
                    <p class="kicker">Folders</p>
                    <h2 class="card-title">Organised experience groups</h2>
                    <div class="folder-cloud">
                        @forelse ($report['folderSummary'] as $folder)
                            <span class="tag">
                                {{ $folder['label'] }}
                                <span class="tag-count">{{ $folder['count'] }}</span>
                            </span>
                        @empty
                            <p class="copy">No folders or grouped collections yet.</p>
                        @endforelse
                    </div>
                </article>
            </section>

            <section class="grid two-col">
                <article class="card">
                    <p class="kicker">Evidence</p>
                    <h2 class="card-title">Highlighted sessions</h2>
                    <div class="evidence-grid">
                        @forelse ($report['evidenceSessions'] as $session)
                            <article class="evidence-card">
                                <div class="evidence-head">
                                    <div>
                                        <p class="kicker">{{ $session['date'] ?? 'Session' }}</p>
                                        <h3 class="evidence-title">{{ $session['title'] }}</h3>
                                    </div>
                                    <div class="hero-facts" style="margin-top: 0;">
                                        <span class="chip">{{ $session['routeCategoryLabel'] }}</span>
                                        <span class="chip">{{ $formatDistance($session['distanceKm']) }}</span>
                                        <span class="chip">{{ $formatDuration($session['durationMinutes']) }}</span>
                                        @if ($session['beaufort'] !== null)
                                            <span class="chip">F{{ $session['beaufort'] }}</span>
                                        @endif
                                    </div>
                                </div>

                                @if ($session['summary'])
                                    <p class="evidence-body">{{ $session['summary'] }}</p>
                                @endif

                                @if ($session['evidenceTags'] !== [])
                                    <div class="evidence-meta">
                                        @foreach ($session['evidenceTags'] as $tag)
                                            <span class="tag">{{ $tag }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </article>
                        @empty
                            <p class="copy">No strong evidence sessions could be highlighted yet because the journal is still empty.</p>
                        @endforelse
                    </div>
                </article>

                <article class="card">
                    <p class="kicker">Reflection</p>
                    <h2 class="card-title">Selected observation excerpts</h2>
                    <div class="note-list">
                        @forelse ($report['noteExcerpts'] as $note)
                            <article class="note-card">
                                <p class="kicker">{{ $note['date'] ?? 'Session' }} · {{ $note['title'] }}</p>
                                <p class="copy" style="margin-top: 12px; font-size: 15px; color: var(--text);">
                                    {{ $note['excerpt'] }}
                                </p>
                                <span class="note-source">{{ $note['source'] }}</span>
                            </article>
                        @empty
                            <p class="copy">No written observations or expedition reflections are saved yet.</p>
                        @endforelse
                    </div>
                </article>
            </section>

            <section class="grid appendix">
                <article class="card">
                    <p class="kicker">Appendix</p>
                    <h2 class="card-title">Full session log</h2>
                    <p class="copy">
                        This appendix keeps the application report honest and complete by listing the full journal, not just the strongest examples.
                    </p>

                    <div class="log-meta">
                        <span class="chip">{{ count($sessionLog) }} sessions listed</span>
                        <span class="chip">{{ $formatDistance($report['headline']['distanceKm'] ?? null) }} total</span>
                        <span class="chip">{{ number_format((float) ($report['headline']['durationHours'] ?? 0), 1) }} h total</span>
                    </div>

                    <div class="log-table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Session</th>
                                    <th>Type</th>
                                    <th>Distance</th>
                                    <th>Duration</th>
                                    <th>Wind</th>
                                    <th>Evidence</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($sessionLog as $session)
                                    <tr>
                                        <td>{{ $session['date'] ?? '-' }}</td>
                                        <td>
                                            <strong>{{ $session['title'] }}</strong>
                                            <div class="table-muted">{{ $session['launchName'] ?? '-' }}</div>
                                            @if ($session['noteExcerpt'])
                                                <div class="table-muted">{{ $session['noteExcerpt'] }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $session['routeCategoryLabel'] }}
                                            @if ($session['isExpedition'])
                                                <div class="table-muted">Expedition</div>
                                            @endif
                                            @if ($session['folders'] !== [])
                                                <div class="table-muted">{{ implode(', ', $session['folders']) }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $formatDistance($session['distanceKm']) }}</td>
                                        <td>{{ $formatDuration($session['durationMinutes']) }}</td>
                                        <td>{{ $session['beaufort'] !== null ? 'F'.$session['beaufort'] : '-' }}</td>
                                        <td>
                                            @php
                                                $evidenceFlags = array_values(array_filter([
                                                    $session['hasTrack'] ? 'Track' : null,
                                                    $session['hasObservation'] ? 'Notes' : null,
                                                    !empty($session['skills']) ? 'Skills' : null,
                                                ]));
                                            @endphp
                                            {{ $evidenceFlags !== [] ? implode(', ', $evidenceFlags) : '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>

            <footer class="footer">
                <span>Generated from Your Kayaking Journal</span>
                <span>{{ $report['profile']['paddlerName'] }} · {{ $report['purpose'] }}</span>
            </footer>
        </main>
    </div>
</body>
</html>
