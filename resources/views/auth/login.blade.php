<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in · Sea Kayak Logbook</title>
    <style>
        :root { color-scheme: light; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        body { margin: 0; background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%); color: #0f172a; }
        .wrap { max-width: 520px; margin: 0 auto; padding: 40px 20px 64px; }
        .card { background: rgba(255,255,255,.96); border: 1px solid #e2e8f0; border-radius: 28px; box-shadow: 0 16px 40px rgba(15,23,42,.06); padding: 28px; }
        .eyebrow { font-size: 12px; font-weight: 700; letter-spacing: .28em; text-transform: uppercase; color: #f97316; }
        h1 { margin: 12px 0 0; font-size: clamp(34px, 5vw, 48px); line-height: 1.02; }
        p { color: #475569; line-height: 1.7; }
        .field { margin-top: 18px; display: grid; gap: 8px; }
        label { font-size: 14px; font-weight: 600; color: #334155; }
        input { width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 16px; padding: 14px 16px; font-size: 16px; }
        .row { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 10px; }
        .button { width: 100%; border: 0; border-radius: 999px; background: #0f172a; color: white; font-weight: 700; padding: 14px 18px; font-size: 15px; cursor: pointer; margin-top: 20px; }
        .muted { font-size: 14px; color: #64748b; }
        .link { color: #0f172a; font-weight: 600; text-decoration: none; }
        .error { margin-top: 8px; color: #dc2626; font-size: 14px; }
        .status { margin-top: 16px; padding: 12px 14px; border-radius: 16px; background: #ecfdf5; color: #047857; font-size: 14px; }
    </style>
</head>
<body>
    <main class="wrap">
        <section class="card">
            <div class="eyebrow">Private access</div>
            <h1>Open your kayak dashboard</h1>
            <p>Use a plain Laravel sign-in flow while we stabilize the richer app shell.</p>

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
        </section>
    </main>
</body>
</html>
