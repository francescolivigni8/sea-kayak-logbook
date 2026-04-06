<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register · Sea Kayak Logbook</title>
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
        .button { width: 100%; border: 0; border-radius: 999px; background: #0f172a; color: white; font-weight: 700; padding: 14px 18px; font-size: 15px; cursor: pointer; margin-top: 20px; }
        .muted { font-size: 14px; color: #64748b; }
        .link { color: #0f172a; font-weight: 600; text-decoration: none; }
        .error { margin-top: 8px; color: #dc2626; font-size: 14px; }
    </style>
</head>
<body>
    <main class="wrap">
        <section class="card">
            <div class="eyebrow">New workspace</div>
            <h1>Create your kayak account</h1>
            <p>Start with a simple Laravel registration flow, then your workspace and logbook will be created for you.</p>

            <form method="POST" action="{{ route('register.store') }}">
                @csrf

                <div class="field">
                    <label for="name">Name</label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" placeholder="Full name">
                    @error('name') <div class="error">{{ $message }}</div> @enderror
                </div>

                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="email@example.com">
                    @error('email') <div class="error">{{ $message }}</div> @enderror
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" required autocomplete="new-password" placeholder="Password">
                    @error('password') <div class="error">{{ $message }}</div> @enderror
                </div>

                <div class="field">
                    <label for="password_confirmation">Confirm password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Confirm password">
                </div>

                <button class="button" type="submit">Create account</button>
            </form>

            <p class="muted" style="margin-top:18px;">
                Already have an account?
                <a class="link" href="{{ route('login') }}">Log in</a>
            </p>
        </section>
    </main>
</body>
</html>
