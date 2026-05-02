<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if (config('kayak.noindex'))
        <meta name="robots" content="noindex, nofollow, noarchive">
    @endif
    <title>Terms · Sea Kayak Logbook</title>
    <link rel="icon" href="/brand/ykj-logo-192.png" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <style>
        body { margin: 0; font-family: Manrope, ui-sans-serif, system-ui, sans-serif; color: #252b52; background: #edf0ff; }
        main { max-width: 760px; margin: 0 auto; padding: 48px 20px 72px; }
        section { border: 1px solid rgba(103, 114, 255, .16); border-radius: 28px; background: rgba(255,255,255,.86); padding: 30px; box-shadow: 0 24px 54px rgba(96,112,186,.14); }
        h1 { margin: 0 0 16px; font-size: clamp(34px, 6vw, 54px); line-height: .96; letter-spacing: -.04em; }
        h2 { margin: 28px 0 10px; font-size: 20px; }
        p, li { line-height: 1.75; color: rgba(37,43,82,.72); }
        ul { margin: 0; padding-left: 20px; }
        a { color: #252b52; font-weight: 700; }
        .footer-note { margin-top: 28px; font-size: 12px; line-height: 1.6; color: rgba(37,43,82,.56); }
    </style>
</head>
<body>
    <main>
        <section>
            <h1>Terms</h1>
            <p>Last updated: {{ config('kayak.legal.terms_version', '2026-05-02') }}.</p>
            <p>Your Kayaking Journal is a personal sea-kayaking journal and planning companion. By creating an account or using the app, you agree to use it responsibly and lawfully.</p>

            <h2>Not a safety or navigation service</h2>
            <p>The app is not a navigation, emergency, safety, weather-warning, tide-warning, route-certification, or rescue service. Do not rely on it as your only source before paddling. Always check official marine forecasts, tide tables, local notices, maps, conditions, training, equipment, and judgement.</p>

            <h2>Weather, tide, and map data</h2>
            <p>Weather, tide, current, map, and routing information may come from third-party providers. It can be delayed, incomplete, inaccurate, unavailable, or unsuitable for sea-kayaking decisions. Planning outputs are guidance only.</p>

            <h2>Your content</h2>
            <p>You remain responsible for the sessions, routes, photos, files, notes, and other content you upload. Do not upload content you do not have permission to use, content that violates someone else's privacy, or content that is unlawful or harmful.</p>

            <h2>Private beta and access</h2>
            <p>The service may be restricted to invited users while it is being tested. Accounts may be limited, suspended, or removed to protect the service, other users, or legal obligations.</p>

            <h2>Public sharing</h2>
            <p>Public read-only sharing is currently disabled by default. If public sharing is enabled later, you will be responsible for checking what you expose before making any route, profile, note, or image public.</p>

            <h2>Acceptable use</h2>
            <ul>
                <li>Do not attempt to access accounts, profiles, files, or systems that are not yours.</li>
                <li>Do not overload imports, weather previews, maps, storage, or third-party services.</li>
                <li>Do not use the app to harass, track, identify, or endanger another person.</li>
                <li>Do not upload malicious files or try to bypass security controls.</li>
            </ul>

            <h2>Availability</h2>
            <p>The service can change, pause, fail, or be withdrawn. During staging and beta periods, features may be experimental and data models may change.</p>

            <h2>Liability</h2>
            <p>The app is provided for journaling and planning support. To the maximum extent allowed by law, the operator is not responsible for paddling decisions, weather decisions, route decisions, injury, loss, or damage arising from use of the app or third-party data.</p>

            <h2>Contact</h2>
            <p>For support, terms questions, or privacy requests, contact <a href="mailto:{{ config('mail.from.address', 'hello@yourkayakingjournal.com') }}">{{ config('mail.from.address', 'hello@yourkayakingjournal.com') }}</a>.</p>
            <p><a href="{{ route('login') }}">Back to login</a></p>
            <p class="footer-note">
                © {{ now()->year }} {{ config('kayak.legal.copyright_owner', 'Francesco Li Vigni') }}.
                {{ config('kayak.legal.product_name', 'Your Kayaking Journal') }}. All rights reserved.
                Use of the service does not transfer ownership of the app, brand, or original design materials.
            </p>
        </section>
    </main>
</body>
</html>
