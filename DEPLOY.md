# Deploying Sea Kayak Logbook

Recommended host: **Laravel Cloud**

Why this is the best fit for this project:

- first-party Laravel hosting
- built-in free `*.laravel.cloud` preview domain after first successful deploy
- custom domain support with automatic TLS
- managed Postgres, queues, cache, and object storage available in the same platform
- good fit for Fortify auth, public profile pages, and uploaded GPX/FIT/photo media

This app is a Laravel + Inertia + Vue application with:

- session auth via Laravel Fortify
- shareable public profile pages
- media uploads for GPX, FIT, and session photos
- optional public disk for local development
- optional object storage for production

## 0. Laravel Cloud path

Laravel Cloud creates applications from an existing Git repository or from a starter kit. For this app, the expected production path is:

1. Push this Laravel project to GitHub, GitLab, or Bitbucket.
2. Create a Laravel Cloud application from that repository.
3. Attach:
   - a managed Postgres database
   - object storage bucket
   - optional queue / worker if mail or future background jobs are queued
4. Set the environment variables from this document.
5. Deploy once and use the generated `*.laravel.cloud` domain for testing.
6. Add your real custom domain after the first successful deploy.

Important:

- Laravel Cloud does **not** support SQLite for production.
- Treat the local filesystem as non-persistent in production and use object storage for media.

## 1. Build and bootstrap

From `/Users/francesco/Documents/New project/sea-kayak-logbook-laravel`:

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If you are using the local `public` disk for media:

```bash
php artisan storage:link
```

## 2. Minimum production environment

Set these before launch:

```bash
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-real-app-url.example
ASSET_URL=

DB_CONNECTION=mysql
DB_HOST=...
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

SESSION_DRIVER=database
SESSION_DOMAIN=your-real-app-url.example
SESSION_SECURE_COOKIE=true

CACHE_STORE=database
QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.example
MAIL_FROM_NAME="${APP_NAME}"
```

## 3. Media storage

### Local/public disk

Good for local dev or a simple single-server deploy:

```bash
FILESYSTEM_DISK=public
KAYAK_MEDIA_DISK=public
```

Requirements:

- `php artisan storage:link`
- persistent shared storage on the host

### S3-compatible production storage

Recommended for real deployment:

```bash
FILESYSTEM_DISK=s3
KAYAK_MEDIA_DISK=s3

AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=...
AWS_BUCKET=...
AWS_URL=
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=false
```

If the project host does not already ship with the S3 adapter, install:

```bash
composer require league/flysystem-aws-s3-v3
```

## 4. Auth and email

This app uses Laravel Fortify for:

- registration
- login
- password reset
- email verification
- optional two-factor authentication

Before launch, confirm:

1. `APP_URL` matches the real site URL.
2. Production mail is configured and working.
3. Password reset emails arrive quickly.
4. Email verification links return to the same app host.

If mail is queued in production, run a queue worker:

```bash
php artisan queue:work
```

## 5. Launch smoke test

Run these checks on the real URL:

1. Register a new user.
2. Verify the email.
3. Log in with password.
4. Request a password reset email.
5. Create a session with photo + GPX.
6. Create a session with FIT only.
7. Open the dashboard, diary, session detail, and public profile.
8. Confirm uploaded media URLs resolve correctly.

## 6. Useful routes after deploy

- Dashboard: `/dashboard`
- Diary: `/diary`
- Sessions: `/sessions`
- Garmin import: `/imports/garmin`
- Public profile: `/p/{slug}`
- Public expeditions: `/p/{slug}/expeditions`
