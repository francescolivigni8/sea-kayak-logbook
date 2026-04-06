<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sea Kayak Logbook Workspace</title>
    <style>
        :root {
            color-scheme: light;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        body {
            margin: 0;
            background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
            color: #0f172a;
        }
        .wrap {
            max-width: 860px;
            margin: 0 auto;
            padding: 40px 20px 64px;
        }
        .card {
            background: rgba(255,255,255,0.96);
            border: 1px solid #e2e8f0;
            border-radius: 28px;
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.06);
            padding: 28px;
        }
        .eyebrow {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.28em;
            text-transform: uppercase;
            color: #f97316;
        }
        h1 {
            margin: 12px 0 0;
            font-size: clamp(32px, 5vw, 52px);
            line-height: 1.02;
        }
        p {
            line-height: 1.7;
            color: #475569;
        }
        .chips, .grid {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        .chip, .tile, .logout {
            border-radius: 999px;
            border: 1px solid #e2e8f0;
            background: #fff;
            padding: 12px 18px;
            text-decoration: none;
            color: #0f172a;
            font-weight: 600;
        }
        .grid {
            margin-top: 20px;
        }
        .tile {
            border-radius: 22px;
            min-width: 180px;
        }
        form {
            margin-top: 24px;
        }
        .logout {
            cursor: pointer;
        }
    </style>
</head>
<body>
    <main class="wrap">
        <section class="card">
            <div class="eyebrow">Logged in</div>
            <h1>Your kayak workspace</h1>
            <p>
                This is a plain server-rendered workspace page. If you can see this after signing in,
                auth and session persistence are working and the remaining issue is in the richer app layer.
            </p>

            <div class="chips">
                <div class="chip">{{ $user->name }}</div>
                <div class="chip">{{ $user->email }}</div>
            </div>

            <div class="grid">
                <a class="tile" href="{{ route('sessions.index') }}">Sessions</a>
                <a class="tile" href="{{ route('dashboard') }}">Dashboard</a>
                <a class="tile" href="{{ route('diary') }}">Diary</a>
                <a class="tile" href="{{ route('expeditions.index') }}">Expeditions</a>
                <a class="tile" href="{{ route('imports.garmin.create') }}">Garmin import</a>
                <a class="tile" href="{{ route('profile.edit') }}">Settings</a>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="logout" type="submit">Log out</button>
            </form>
        </section>
    </main>
</body>
</html>
