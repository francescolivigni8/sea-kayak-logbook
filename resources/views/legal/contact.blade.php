<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if (config('kayak.noindex'))
        <meta name="robots" content="noindex, nofollow, noarchive">
    @endif
    <title>Contact · Sea Kayak Logbook</title>
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
            <h1>Contact</h1>
            <p>For staging feedback, access problems, account deletion questions, or privacy requests, contact the app owner directly.</p>
            <p>Before full public launch, this page should be updated with the final support email for Your Kayaking Journal.</p>
            <p><a href="{{ route('login') }}">Back to login</a></p>
        </section>
    </main>
</body>
</html>
