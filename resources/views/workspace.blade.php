<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sea Kayak Logbook Workspace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=Manrope:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap"
        rel="stylesheet"
    >
    <style>
        :root {
            color-scheme: light;
            font-family: "Manrope", ui-sans-serif, system-ui, sans-serif;
        }
        body {
            margin: 0;
            background:
                linear-gradient(90deg, rgba(103, 114, 255, 0.14), rgba(255, 156, 107, 0.08) 24%, transparent 58%),
                radial-gradient(circle at 12% 0%, rgba(122, 215, 208, 0.14), transparent 26%),
                linear-gradient(180deg, #f5f6ff 0%, #edf0ff 100%);
            color: #252b52;
        }
        .wrap {
            max-width: 860px;
            margin: 0 auto;
            padding: 40px 20px 64px;
        }
        .card {
            background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(255,255,255,.88));
            border: 1px solid rgba(103, 114, 255, 0.16);
            border-radius: 30px;
            box-shadow: 0 24px 54px rgba(96, 112, 186, 0.14);
            padding: 28px;
        }
        .eyebrow {
            font-family: "IBM Plex Mono", ui-monospace, monospace;
            font-size: 12px;
            font-weight: 500;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: #ff9c6b;
        }
        h1 {
            margin: 12px 0 0;
            font-family: "Sora", "Manrope", ui-sans-serif, system-ui, sans-serif;
            font-size: clamp(32px, 5vw, 52px);
            line-height: .98;
            letter-spacing: -.04em;
        }
        p {
            line-height: 1.7;
            color: rgba(37, 43, 82, 0.68);
        }
        .chips, .grid {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        .chip, .tile, .logout {
            border-radius: 999px;
            border: 1px solid rgba(103, 114, 255, 0.16);
            background: rgba(255,255,255,.82);
            padding: 12px 18px;
            text-decoration: none;
            color: #252b52;
            font-weight: 600;
        }
        .grid {
            margin-top: 20px;
        }
        .tile {
            border-radius: 22px;
            min-width: 180px;
        }
        .tile small {
            display: block;
            margin-top: 6px;
            color: rgba(37, 43, 82, 0.64);
            font-weight: 500;
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
                This lighter fallback stays here in case the richer journal screens ever need a recovery route.
            </p>

            <div class="chips">
                <div class="chip">{{ $user->name }}</div>
                <div class="chip">{{ $user->email }}</div>
            </div>

            <div class="grid">
                <a class="tile" href="{{ route('dashboard') }}">Dashboard<small>Charts, exposure, route map</small></a>
                <a class="tile" href="{{ route('diary') }}">Diary<small>Calendar and session reading view</small></a>
                <a class="tile" href="{{ route('sessions.index') }}">Library<small>All paddles in one archive</small></a>
                <a class="tile" href="{{ route('expeditions.index') }}">Expeditions<small>Multiday places and atlas</small></a>
                <a class="tile" href="{{ route('imports.garmin.create') }}">Import<small>Garmin CSV, GPX, and FIT</small></a>
                <a class="tile" href="{{ route('profile.edit') }}">Account<small>Profile, security, appearance</small></a>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="logout" type="submit">Log out</button>
            </form>
        </section>
    </main>
</body>
</html>
