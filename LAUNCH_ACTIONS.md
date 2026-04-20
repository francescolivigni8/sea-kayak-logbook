# Launch Action Map

This file parks the remaining launch work so we can switch context and return later without losing the thread.

## Current status

The Laravel app is code-ready for private staging, but not yet ready for a wider public launch. The remaining work is mostly external production setup: domain, mail, Laravel Cloud environment, storage verification, smoke testing, and monitoring/backups.

Status checked on 2026-04-20:

- Local launch-readiness tests pass.
- `http://yourkayakingjournal.com` still returns `302` from `Namecheap URL Forward` to `http://www.yourkayakingjournal.com/`.
- `https://yourkayakingjournal.com` times out instead of serving Laravel Cloud.
- `https://www.yourkayakingjournal.com` fails TLS with an unrecognized host name.
- Conclusion: Namecheap forwarding/parking is still in the way and the custom domain is not yet connected to Laravel Cloud.

Latest pushed commits at the time this was saved:

- `61b23ba` - Clarify collapsible library sections
- `f0c89e1` - Refine planning weather map controls
- `b9ce32b` - Stabilize cloud frontend build

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

Current finding: `yourkayakingjournal.com` is not serving the Laravel Cloud app yet. The latest check still shows Namecheap forwarding on HTTP, a timeout on root HTTPS, and invalid/unconfigured TLS on `www`.

Action needed:

- Add the custom domain in Laravel Cloud.
- Copy the DNS target records Laravel Cloud provides.
- Replace Namecheap URL Forward/parking with the required DNS records.
- Verify both `yourkayakingjournal.com` and `www.yourkayakingjournal.com`.
- Confirm SSL/HTTPS is issued and valid.

Recommended Laravel Cloud choices:

- Add `yourkayakingjournal.com` as the custom domain.
- Use the default/simple verification path unless Laravel Cloud specifically asks for pre-verification records.
- Enable the Laravel Cloud redirect between root and `www` only after DNS records are copied exactly.
- In Namecheap, use `@` for the root host and `www` for the `www` host when entering records, unless Laravel Cloud shows a different host value.

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
```

Important:

- Do not set `SESSION_DOMAIN` unless we deliberately need a custom cookie plan.
- Keep public profiles disabled for private staging.
- Keep noindex enabled until public launch is intentional.

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

1. Connect domain in Laravel Cloud and Namecheap.
2. Set final Laravel Cloud env variables.
3. Configure real mail.
4. Verify private object storage.
5. Redeploy from `main`.
6. Run the smoke test.
7. Configure monitoring/backups.
8. Decide whether private feedback can start.
