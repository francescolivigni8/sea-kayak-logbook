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
            <h1>Privacy</h1>
            <p>Last updated: {{ config('kayak.legal.privacy_version', '2026-05-02') }}.</p>
            <p>Your Kayaking Journal is a private-first kayaking journal for sessions, routes, planning, weather notes, uploaded activity files, photos, and account settings. Public sharing is disabled by default.</p>

            <h2>Who controls the data</h2>
            <p>The app is operated by Francesco Li Vigni. For privacy questions, access requests, correction requests, deletion questions, or export help, contact <a href="mailto:{{ config('mail.from.address', 'hello@yourkayakingjournal.com') }}">{{ config('mail.from.address', 'hello@yourkayakingjournal.com') }}</a>.</p>

            <h2>What we collect</h2>
            <ul>
                <li>Account details such as name, email address, password hash, session cookies, and two-factor settings if enabled.</li>
                <li>Profile settings such as paddler name, home water, map defaults, club text, kayaks, and paddles.</li>
                <li>Logged and planned paddles, including dates, launch and landing points, route points, folders, notes, gear used, rescue/development fields, and expedition tags.</li>
                <li>Uploaded GPX, FIT, and photo files. Photos are re-encoded by the app to reduce embedded metadata before storage.</li>
                <li>Weather and marine details that you enter manually or ask the app to fetch for a session or planned route.</li>
                <li>Technical records such as application logs, security events, error reports, and basic request metadata needed to run and protect the service.</li>
            </ul>

            <h2>Why we use it</h2>
            <ul>
                <li>To provide the journal, maps, imports, planning tools, folders, and account settings you ask to use.</li>
                <li>To protect accounts, prevent abuse, debug errors, and keep the service reliable.</li>
                <li>To send account email such as password reset messages when email delivery is enabled.</li>
                <li>To improve the product only when privacy-safe analytics are enabled and disclosed.</li>
            </ul>

            <h2>Legal basis</h2>
            <p>For most account and journal features, we process data because it is necessary to provide the service you request. Some operational processing is based on legitimate interests, such as security, debugging, abuse prevention, and service reliability. Where optional analytics or public sharing are introduced later, the app will use the appropriate consent or privacy controls before enabling them.</p>

            <h2>Third-party services</h2>
            <p>The app may use Laravel Cloud, managed database/storage providers, email delivery providers such as Resend, map and weather providers such as MapTiler, Stormglass, Open-Meteo, and MET Norway, and error monitoring such as Sentry. When you use maps or weather features, approximate locations, route areas, times, technical request data, or error details may be sent to those providers so the feature can work.</p>

            <h2>Public sharing</h2>
            <p>Public profiles and public read-only session pages are currently disabled by default. Until that changes, your journal is intended to be visible only to authenticated users who can access the profile.</p>

            <h2>Retention and deletion</h2>
            <p>Journal data is kept while your account exists. You can delete your account from account settings; this deletes your owned profile data and uploaded media from active storage. Backups, logs, and provider records may take longer to expire according to operational retention schedules.</p>

            <h2>Your rights</h2>
            <p>Depending on where you live, you may have rights to access, correct, export, delete, restrict, or object to processing of your personal data. Account settings include a JSON data export and account deletion. You can also contact us for help with a privacy request.</p>

            <h2>Cookies and analytics</h2>
            <p>The app uses essential cookies for login sessions and lightweight preferences such as appearance. Product analytics are disabled unless explicitly enabled in the environment and described here before broader public use.</p>

            <h2>Safety note</h2>
            <p>Kayaking routes and weather notes can reveal sensitive location patterns. Treat exported files and screenshots carefully, especially before sharing them outside the app.</p>
            <p><a href="{{ route('login') }}">Back to login</a></p>
            <p class="footer-note">
                © {{ now()->year }} {{ config('kayak.legal.copyright_owner', 'Francesco Li Vigni') }}.
                {{ config('kayak.legal.product_name', 'Your Kayaking Journal') }}. All rights reserved.
                The app name, branding, layouts, and original journal content remain protected by applicable copyright and other rights.
            </p>
        </section>
    </main>
</body>
</html>
