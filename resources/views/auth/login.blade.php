<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in · Sea Kayak Logbook</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=Manrope:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap"
        rel="stylesheet"
    >
    <link rel="icon" href="/brand/ykj-logo-192.png" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <style>
        :root {
            color-scheme: light;
            font-family: "Manrope", ui-sans-serif, system-ui, sans-serif;
            --text: #252b52;
            --muted: rgba(37, 43, 82, 0.68);
            --line: rgba(103, 114, 255, 0.16);
        }
        body {
            margin: 0;
            min-height: 100vh;
            background:
                linear-gradient(90deg, rgba(103, 114, 255, 0.14), rgba(255, 156, 107, 0.08) 24%, transparent 58%),
                radial-gradient(circle at 12% 0%, rgba(122, 215, 208, 0.14), transparent 26%),
                linear-gradient(180deg, #f5f6ff 0%, #edf0ff 100%);
            color: var(--text);
        }
        .wrap { max-width: 620px; margin: 0 auto; padding: 40px 20px 64px; }
        .card {
            background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(255,255,255,.88));
            border: 1px solid var(--line);
            border-radius: 30px;
            box-shadow: 0 24px 54px rgba(96, 112, 186, 0.14);
            padding: 30px;
            backdrop-filter: blur(18px);
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .brand-logo {
            width: 92px;
            height: 92px;
            flex: 0 0 auto;
            border-radius: 24px;
            border: 1px solid rgba(103, 114, 255, 0.16);
            object-fit: cover;
            box-shadow: 0 18px 34px rgba(37, 43, 82, 0.14);
        }
        .brand-copy { min-width: 0; }
        .eyebrow {
            font-family: "IBM Plex Mono", ui-monospace, monospace;
            font-size: 12px;
            font-weight: 500;
            letter-spacing: .22em;
            text-transform: uppercase;
            color: #ff9c6b;
        }
        h1 {
            margin: 12px 0 0;
            font-family: "Sora", "Manrope", ui-sans-serif, system-ui, sans-serif;
            font-size: clamp(34px, 5vw, 48px);
            line-height: .98;
            letter-spacing: -.04em;
        }
        p { color: var(--muted); line-height: 1.7; }
        .chips {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }
        .chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,.82);
            color: rgba(37, 43, 82, 0.72);
            font-family: "IBM Plex Mono", ui-monospace, monospace;
            font-size: 12px;
        }
        .field { margin-top: 18px; display: grid; gap: 8px; }
        label { font-size: 14px; font-weight: 600; color: var(--text); }
        input {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid rgba(103, 114, 255, 0.2);
            border-radius: 18px;
            padding: 14px 16px;
            font-size: 16px;
            color: var(--text);
            background: rgba(255,255,255,.84);
        }
        .row { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 10px; }
        .button {
            width: 100%;
            border: 0;
            border-radius: 999px;
            background: linear-gradient(135deg, rgba(103,114,255,.98), rgba(122,162,255,.94));
            color: white;
            font-weight: 700;
            padding: 14px 18px;
            font-size: 15px;
            cursor: pointer;
            margin-top: 20px;
            box-shadow: 0 16px 32px rgba(103,114,255,.24);
        }
        .muted { font-size: 14px; color: var(--muted); }
        .link { color: var(--text); font-weight: 700; text-decoration: none; }
        .legal { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 20px; font-size: 13px; }
        .error { margin-top: 8px; color: #dc2626; font-size: 14px; }
        .status {
            margin-top: 16px;
            padding: 12px 14px;
            border-radius: 18px;
            border: 1px solid rgba(137,223,171,.5);
            background: rgba(241,255,245,.9);
            color: #047857;
            font-size: 14px;
        }
        @media (max-width: 520px) {
            .brand { align-items: flex-start; }
            .brand-logo { width: 66px; height: 66px; border-radius: 18px; }
        }
    </style>
</head>
<body>
    <main class="wrap">
        <section class="card">
            <div class="brand">
                <img class="brand-logo" src="/brand/ykj-logo-clean.png" alt="Your Kayaking Journal logo" width="92" height="92">
                <div class="brand-copy">
                    <div class="eyebrow">Private access</div>
                    <h1>Open your kayak dashboard</h1>
                </div>
            </div>
            <p>Password-first access to the lighter journal workspace.</p>

            <div class="chips">
                <div class="chip">Email + password</div>
                <div class="chip">Private workspace</div>
                <div class="chip">Profile setup first</div>
            </div>

            @if ($status)
                <div class="status">{{ $status }}</div>
            @endif

            <form method="POST" action="{{ route('login.store') }}">
                @csrf

                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="email@example.com">
                    @error('email') <div class="error">{{ $message }}</div> @enderror
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Password">
                    @error('password') <div class="error">{{ $message }}</div> @enderror
                </div>

                <div class="row">
                    <label class="muted" style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="remember" style="width:auto;"> Remember me
                    </label>
                    @if ($canResetPassword)
                        <a class="link" href="{{ route('password.request') }}">Forgot password?</a>
                    @endif
                </div>

                <button class="button" type="submit">Log in</button>
            </form>

            @if ($canRegister)
                <p class="muted" style="margin-top:18px;">
                    Don’t have an account?
                    <a class="link" href="{{ route('register') }}">Create one</a>
                </p>
            @endif
            <div class="legal">
                <a class="link" href="{{ route('legal.privacy') }}">Privacy</a>
                <a class="link" href="{{ route('legal.terms') }}">Terms</a>
                <a class="link" href="{{ route('legal.contact') }}">Contact</a>
            </div>
        </section>
    </main>
</body>
</html>
