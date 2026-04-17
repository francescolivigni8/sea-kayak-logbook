<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if (config('kayak.noindex'))
        <meta name="robots" content="noindex, nofollow, noarchive">
    @endif
    <title>Privacy · Sea Kayak Logbook</title>
    <link rel="icon" href="/brand/ykj-logo-192.png" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <style>
        body { margin: 0; font-family: Manrope, ui-sans-serif, system-ui, sans-serif; color: #252b52; background: #edf0ff; }
        main { max-width: 760px; margin: 0 auto; padding: 48px 20px 72px; }
        section { border: 1px solid rgba(103, 114, 255, .16); border-radius: 28px; background: rgba(255,255,255,.86); padding: 30px; box-shadow: 0 24px 54px rgba(96,112,186,.14); }
        h1 { margin: 0 0 16px; font-size: clamp(34px, 6vw, 54px); line-height: .96; letter-spacing: -.04em; }
        p { line-height: 1.75; color: rgba(37,43,82,.72); }
        a { color: #252b52; font-weight: 700; }
    </style>
</head>
<body>
    <main>
        <section>
            <h1>Privacy</h1>
            <p>Sea Kayak Logbook is currently a private-first journal for kayaking sessions, routes, weather notes, uploads, and account settings.</p>
            <p>We store the information needed to run the app: account details, paddling sessions, map points/routes, uploaded GPX/FIT/photo files, gear/profile settings, and optional weather data filled from external providers.</p>
            <p>Public sharing is disabled by default for this launch. We will decide what becomes public read-only later.</p>
            <p>For privacy questions or deletion requests, use the contact page or delete your account from the account settings screen.</p>
            <p><a href="{{ route('login') }}">Back to login</a></p>
        </section>
    </main>
</body>
</html>
