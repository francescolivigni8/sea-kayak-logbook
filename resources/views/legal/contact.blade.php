<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if (config('kayak.noindex'))
        <meta name="robots" content="noindex, nofollow, noarchive">
    @endif
    <title>Contact · Sea Kayak Logbook</title>
    <link rel="icon" href="/brand/ykj-logo-192.png" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <style>
        body { margin: 0; font-family: Manrope, ui-sans-serif, system-ui, sans-serif; color: #252b52; background: #edf0ff; }
        main { max-width: 760px; margin: 0 auto; padding: 48px 20px 72px; }
        section { border: 1px solid rgba(103, 114, 255, .16); border-radius: 28px; background: rgba(255,255,255,.86); padding: 30px; box-shadow: 0 24px 54px rgba(96,112,186,.14); }
        h1 { margin: 0 0 16px; font-size: clamp(34px, 6vw, 54px); line-height: .96; letter-spacing: -.04em; }
        h2 { margin: 28px 0 10px; font-size: 20px; }
        p { line-height: 1.75; color: rgba(37,43,82,.72); }
        a { color: #252b52; font-weight: 700; }
        .footer-note { margin-top: 28px; font-size: 12px; line-height: 1.6; color: rgba(37,43,82,.56); }
    </style>
</head>
<body>
    <main>
        <section>
            <h1>Contact</h1>
            <p>For staging feedback, access problems, account deletion questions, privacy requests, or safety/disclaimer questions about Your Kayaking Journal, contact:</p>
            <h2><a href="mailto:{{ config('mail.from.address', 'hello@yourkayakingjournal.com') }}">{{ config('mail.from.address', 'hello@yourkayakingjournal.com') }}</a></h2>
            <p>If your message is about account access, include the email address used for the account. Do not email sensitive GPX/FIT files or passwords.</p>
            <p><a href="{{ route('login') }}">Back to login</a></p>
            <p class="footer-note">
                © {{ now()->year }} {{ config('kayak.legal.copyright_owner', 'Francesco Li Vigni') }}.
                {{ config('kayak.legal.product_name', 'Your Kayaking Journal') }}. All rights reserved.
            </p>
        </section>
    </main>
</body>
</html>
