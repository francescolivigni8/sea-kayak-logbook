# Deploying Sea Kayak Logbook

Recommended host: **Laravel Cloud**.

This app is currently launch-hardened as a **private-first** Laravel + Inertia + Vue app. Public profile sharing code still exists behind a feature flag, but it is disabled by default until we intentionally decide what should be exposed read-only.

## 1. Required Laravel Cloud resources

Attach these resources to the environment before launch:

- Managed Postgres database
- Object storage bucket for GPX, FIT, and session photos
- Optional queue/worker later if mail or imports move to background jobs

Laravel Cloud injects database and storage credentials for attached resources. Do not manually copy database passwords into custom env vars unless Cloud specifically tells you to.

## 2. Known-good production env

Use this as the canonical Laravel Cloud baseline:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-current-laravel-cloud-or-custom-domain

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

- Do **not** set `SESSION_DOMAIN` for the Laravel Cloud staging domain. We already confirmed that forcing this value can break login/session cookies.
- Keep `KAYAK_NOINDEX=true` while this is staging/private-feedback.
- Keep `KAYAK_PUBLIC_PROFILES_ENABLED=false` until the public read-only surface is deliberately relaunched.
- Use private object storage for GPX, FIT, and photos. `KAYAK_MEDIA_TEMPORARY_URLS=true` makes generated media links expire instead of behaving like permanent public URLs.

## 3. Mail requirement

Password reset is enabled, so real production mail is required before opening this to real users.

Staging can temporarily use:

```env
MAIL_MAILER=log
```

Real launch should use a real provider, for example SMTP, Postmark, Resend, Mailgun, or SES:

```env
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourkayakingjournal.com
MAIL_FROM_NAME="Sea Kayak Logbook"
```

Before launch, confirm:

- password reset email arrives
- reset link opens the same app host set in `APP_URL`
- sender domain/SPF/DKIM is configured with the mail provider

Email verification is currently disabled for this private-first launch. Do not add it to the launch checklist unless we re-enable the Fortify feature.

## 4. Build commands

Use these build commands:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Do **not** run `php artisan db:seed --force` in production.

## 5. Deploy commands

Use this deploy command:

```bash
php artisan migrate --force
```

Do not use `migrate:fresh` in production once real data exists.

## 6. Launch smoke test

Run these on the real staging/live URL after every launch candidate deploy:

1. Register a new user.
2. Complete profile setup.
3. Log out and log back in; confirm the user lands on `/dashboard`.
4. Request a password reset and complete it through real email.
5. Create a manual session with a map point.
6. Create an expedition session with a map point and confirm the world map pin appears.
7. Import Garmin CSV plus GPX/FIT when available.
8. Use Stormglass autofill and confirm wind, Beaufort, tide, and environmental checklist fields populate.
9. Upload a photo and confirm the media URL resolves.
10. Delete a test account with uploaded media and confirm the related GPX/FIT/photo objects are removed from storage.
11. Confirm `/insights/users` is visible only to the email in `KAYAK_OWNER_EMAILS`.
12. Confirm `/p/{slug}` and public expedition URLs return `404` while `KAYAK_PUBLIC_PROFILES_ENABLED=false`.
13. Confirm responses include `X-Robots-Tag: noindex, nofollow, noarchive` while `KAYAK_NOINDEX=true`.

## 7. Useful private routes

- Dashboard: `/dashboard`
- Diary: `/diary`
- Sessions/library: `/sessions`
- Add session: `/sessions/create`
- Garmin import: `/imports/garmin`
- Expedition atlas: `/expeditions`
- Observations: `/observations`
- Expedition notes: `/expedition-notes`
- Owner user insights: `/insights/users`

## 8. Later public launch

When we decide to expose a public read-only surface:

1. Design the exact public profile/session privacy model.
2. Add UI controls for what can be exposed.
3. Re-enable with `KAYAK_PUBLIC_PROFILES_ENABLED=true`.
4. Decide whether `KAYAK_NOINDEX` should remain true or move to false.
5. Re-run a public-route smoke test.
