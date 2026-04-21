# Sea Kayak Logbook (Laravel + Vue)

This is the Laravel + Vue rebuild of the earlier static prototype in:

- `/Users/francesco/Documents/New project/sea-kayak-logbook`

The pivot keeps the kayak-specific product model, but moves auth, profiles, sessions, and future media uploads onto a real application stack.

## Current shape

- Laravel 13 + Inertia + Vue
- SQLite for local development
- First-party auth from the Laravel starter
- Real kayak domain tables:
    - `profiles`
    - `profile_memberships`
    - `paddle_sessions`
- Dashboard tied to the authenticated user profile
- Real session management slice:
    - sessions index
    - manual create/edit form
    - GPX/FIT/photo attachment fields
    - expedition and notes fields
- Diary/calendar experience
- Expedition atlas and world-footprint maps
- Garmin CSV import page with optional GPX and FIT matching
- GPX and FIT parsing into route geometry, timing, and session metrics
- Private-first launch posture; public profile routes are disabled by default behind `KAYAK_PUBLIC_PROFILES_ENABLED`

## Local commands

From `/Users/francesco/Documents/New project/sea-kayak-logbook-laravel`:

```bash
php artisan migrate:fresh --seed
php artisan test
npm run build
php artisan serve
php artisan storage:link
```

If you want the dev server too:

```bash
npm run dev
```

## Node note

This starter currently wants a newer Node runtime than the machine default. To keep the global setup untouched, the project now uses:

- `.nvmrc` with `22.14.0`
- a local `node` package
- helper scripts in `scripts/` so `npm run build` and `npm run dev` can re-exec Vite with the local runtime, including the platform-specific package fallback

On fresh installs, `npm install` triggers the local-node setup automatically through `postinstall`.

## Data model notes

`profiles`

- ownership, slug, home water, timezone, private-first profile state

`profile_memberships`

- many-to-many user access to profiles

`paddle_sessions`

- trip basics
- sea conditions
- severity checklist values
- rescue counters
- expedition flags and days out
- observation and private note fields
- expedition notes
- GPX/FIT file references
- session photo references

## Production notes

- Local media defaults to the `public` disk. Run `php artisan storage:link` locally.
- For production object storage, set `FILESYSTEM_DISK=s3` and `KAYAK_MEDIA_DISK=s3`.
- For private production media links, keep the bucket private and set `KAYAK_MEDIA_TEMPORARY_URLS=true`.
- To unlock the internal users/insights page, set `KAYAK_OWNER_EMAILS` to a comma-separated list of owner emails.
- To enable Stormglass weather autofill on manual sessions and Garmin imports, set `STORMGLASS_API_KEY` in the environment. The app derives Beaufort from returned wind speed automatically.
- Keep `KAYAK_PUBLIC_PROFILES_ENABLED=false` for the private-first launch. Public read-only sharing can be reintroduced later.
- Keep `KAYAK_NOINDEX=true` for staging/private feedback unless we intentionally want search indexing.
- Password reset and login use Laravel Fortify, so production mail delivery matters before launch. Email verification is currently disabled.
- Deployment/auth/storage steps are documented in [DEPLOY.md](/Users/francesco/Documents/New project/sea-kayak-logbook-laravel/DEPLOY.md).
- End-user app instructions are documented in [docs/user-manual.md](/Users/francesco/Documents/New project/sea-kayak-logbook-laravel/docs/user-manual.md).

## Next slices

1. Extend FIT-derived charts with heart rate and temperature overlays where the file contains them
2. Decide the future public read-only profile/session exposure model
3. Add profile switching and shared-membership management UI
4. Deploy the Laravel app on its real production host and wire production mail
