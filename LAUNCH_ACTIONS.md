# Launch Action Map

This file parks the remaining launch work so we can switch context and return later without losing the thread.

## Current status

The Laravel app is code-ready for private staging, but not yet ready for a wider public launch. The remaining work is mostly external production setup: domain, mail, Laravel Cloud environment, storage verification, smoke testing, and monitoring/backups.

Status checked on 2026-04-20 at 13:43 Atlantic/Reykjavik:

- Local launch-readiness tests pass.
- Local health-check test passes.
- Public Google and Cloudflare DNS resolve both `yourkayakingjournal.com` and `www.yourkayakingjournal.com` to `103.133.1.1`.
- Direct HTTPS verification against the Laravel Cloud IP succeeds.
- `https://yourkayakingjournal.com` redirects guests to `/login`.
- `https://www.yourkayakingjournal.com` redirects to `https://yourkayakingjournal.com/`.
- `https://yourkayakingjournal.com/health` returns `200`.
- `X-Robots-Tag: noindex, nofollow, noarchive` is present.
- Secure session cookies are present.
- Local resolver caches may temporarily continue showing the old Namecheap forwarding IP during DNS propagation.

Latest pushed commits at the time this was saved:

- `fee57ce` - Separate planning map controls
- `1c4e465` - Fill planning marine forecast gaps
- `009bc07` - Remove planning forecast summary row
- `94b1fd9` - Cap planning forecast offset
- `50528ea` - Restore planning segment distance labels
- `017626e` - Refine planning map weather toggle
- `d73d516` - Refresh launch action map

## What Codex can fix handsfree

- Code-side launch readiness checks.
- Automated tests and regression tests.
- Frontend/PHP formatting, linting, and type-checking.
- Private media URL handling in the app.
- Account-deletion cleanup for uploaded GPX/FIT/photo files.
- Route throttling for Stormglass-heavy endpoints.
- Deployment documentation and smoke-test scripts/checklists.

## What Codex can mostly drive with minimal input

- Laravel Cloud environment variable setup, if the user provides access/screenshots or confirms values.
- Storage verification, if Laravel Cloud bucket settings can be seen or shared.
- Smoke-test execution after a real deployment URL is reachable.
- Domain verification after DNS records are changed.
- Mail verification after a provider is selected and credentials/DNS records exist.

## What requires external account action

- Domain DNS changes in Namecheap.
- Custom domain attachment and SSL provisioning in Laravel Cloud.
- Mail-provider account setup, sender-domain verification, and SPF/DKIM records.
- Laravel Cloud backup, object-storage retention, logging, and alert settings.

## Remaining launch issues

### Domain

Status: connected.

Verified:

- Namecheap `@` A record points to `103.133.1.1`.
- Namecheap `www` A record points to `103.133.1.1`.
- Laravel Cloud marks the custom domain connected.
- Root HTTPS serves the app and redirects guests to `/login`.
- `www` redirects to the root domain.
- `/health` returns `200`.

Watch item:

- Some resolvers may keep the old Namecheap forwarding/parking response during DNS propagation. Re-check after the TTL expires before sharing widely.

### Mail

Password reset is enabled, so real email delivery is required before real users.

Action needed:

- Choose a provider: Resend, Postmark, Mailgun, SES, or SMTP.
- Configure sender domain for `yourkayakingjournal.com`.
- Add SPF/DKIM DNS records.
- Set Laravel Cloud mail environment variables.
- Test password reset from the live domain.

### Laravel Cloud environment

Required baseline:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourkayakingjournal.com

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true

CACHE_STORE=database
QUEUE_CONNECTION=database

FILESYSTEM_DISK=s3
KAYAK_MEDIA_DISK=s3
KAYAK_MEDIA_TEMPORARY_URLS=true
KAYAK_MEDIA_TEMPORARY_URL_MINUTES=30

KAYAK_PUBLIC_PROFILES_ENABLED=false
KAYAK_NOINDEX=true
KAYAK_OWNER_EMAILS=your-owner-login-email@example.com

STORMGLASS_API_KEY=your-stormglass-api-key
STORMGLASS_SOURCE=none
OPEN_METEO_ENABLED=true

MAP_PROVIDER=maptiler
MAPTILER_API_KEY=your-maptiler-api-key
MAPTILER_WEATHER_ENABLED=true

SENTRY_LARAVEL_DSN=your-sentry-dsn
SENTRY_SAMPLE_RATE=1.0
SENTRY_SEND_DEFAULT_PII=false

POSTHOG_ENABLED=false
```

Important:

- Do not set `SESSION_DOMAIN` unless we deliberately need a custom cookie plan.
- Keep public profiles disabled for private staging.
- Keep noindex enabled until public launch is intentional.
- Keep PostHog disabled until the privacy/consent wording is final.

### Storage

The app now supports expiring media URLs and account-deletion media cleanup. Laravel Cloud storage still needs verification.

Action needed:

- Confirm object storage is attached.
- Confirm the bucket is private, not public.
- Confirm `KAYAK_MEDIA_TEMPORARY_URLS=true`.
- Upload a photo/GPX/FIT and verify the generated URLs work while logged in.
- Delete a test account and confirm uploaded media is removed.

### Smoke test

Run after every serious deployment candidate:

1. Register a new user.
2. Complete profile setup.
3. Log out and log back in; confirm redirect to `/dashboard`.
4. Request and complete password reset through real email.
5. Create a manual session with a map point.
6. Create an expedition session with a map point and confirm the world-map pin appears.
7. Import Garmin CSV plus GPX/FIT where available.
8. Use Stormglass autofill and confirm wind, Beaufort, tide, and environmental checklist fields populate.
9. Upload a photo and confirm media URL behavior.
10. Delete a test account with media and confirm GPX/FIT/photo cleanup.
11. Confirm `/insights/users` is visible only to `KAYAK_OWNER_EMAILS`.
12. Confirm `/p/{slug}` and public expedition URLs return `404`.
13. Confirm responses include `X-Robots-Tag: noindex, nofollow, noarchive`.

### Monitoring and backups

Action needed:

- Confirm Laravel Cloud Postgres backups are active.
- Confirm object storage retention/backups.
- Confirm deploy failure emails are enabled.
- Add or configure error monitoring if available.
- Add uptime monitoring for the final domain.

## Recommended order when resuming

1. Set and confirm final Laravel Cloud env variables.
2. Configure real mail.
3. Verify private object storage.
4. Redeploy from `main` if any env cache changes require it.
5. Run the smoke test.
6. Configure monitoring/backups.
7. Decide whether private feedback can start.
