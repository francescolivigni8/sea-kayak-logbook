# Launch Stack

This is the current third-party stack we are preparing for `yourkayakingjournal.com`.

## Forecasts

- Stormglass remains the primary paid marine provider for saved sessions, Garmin imports, and planning when the key works.
- Open-Meteo Marine is now the planning fallback. It gives wave height, swell, sea temperature, ocean current velocity/direction, and sea-level-height-derived tide state. It is planning guidance only, not navigation authority.
- MET Norway is configured as a future atmospheric enrichment source. It requires a clear `MET_NO_USER_AGENT`.

Laravel Cloud env:

```dotenv
STORMGLASS_API_KEY=
STORMGLASS_SOURCE=none
OPEN_METEO_ENABLED=true
OPEN_METEO_API_KEY=
MET_NO_ENABLED=false
MET_NO_USER_AGENT="Your Kayaking Journal/1.0 hello@yourkayakingjournal.com"
```

## Maps

- The app now reads tile URLs from shared server config.
- Without a MapTiler key, it keeps the current OpenTopoMap/CARTO/OpenStreetMap fallbacks.
- With a MapTiler key, set `MAP_PROVIDER=maptiler` and `MAPTILER_API_KEY=...`.
- Planning can show animated MapTiler Weather layers for wind, rain, radar, pressure, and temperature. The route editor still uses the normal map; the weather map mirrors the planned course as a visual context layer.

Laravel Cloud env:

```dotenv
MAP_PROVIDER=maptiler
MAPTILER_API_KEY=
MAPTILER_WEATHER_ENABLED=true
```

## Email

Use Resend SMTP first. It avoids adding another Laravel mail transport package and supports password resets once DNS is verified.

Laravel Cloud env:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.resend.com
MAIL_PORT=587
MAIL_USERNAME=resend
MAIL_PASSWORD="${RESEND_API_KEY}"
MAIL_FROM_ADDRESS="hello@yourkayakingjournal.com"
MAIL_FROM_NAME="${APP_NAME}"
RESEND_API_KEY=
```

## Monitoring

- Better Stack can monitor `/health`, which now checks app, database, and cache reachability.
- Sentry backend error reporting uses the official `sentry/sentry-laravel` SDK.

Laravel Cloud env:

```dotenv
BETTER_STACK_UPTIME_MONITOR_URL="${APP_URL}/health"
BETTER_STACK_HEARTBEAT_URL=
SENTRY_LARAVEL_DSN=
VITE_SENTRY_DSN=
SENTRY_SAMPLE_RATE=1.0
SENTRY_TRACES_SAMPLE_RATE=
SENTRY_SEND_DEFAULT_PII=false
```

## Product Insights

PostHog is wired as a lightweight, anonymous pageview capture from the journal layout. Keep it disabled until the privacy/consent stance is final.

Laravel Cloud env:

```dotenv
POSTHOG_ENABLED=false
POSTHOG_KEY=
POSTHOG_HOST=https://eu.i.posthog.com
```

## Still Manual

- Create/verify MapTiler, Resend, Better Stack, Sentry, and PostHog accounts.
- Add the real env values in Laravel Cloud.
- Connect DNS for `yourkayakingjournal.com`.
- Add `APP_URL=https://yourkayakingjournal.com` once DNS is live.
