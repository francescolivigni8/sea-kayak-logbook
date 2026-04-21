# Your Kayaking Journal User Manual

Last updated: 21 April 2026

Your Kayaking Journal is a private sea-kayaking logbook for recording paddles, importing Garmin history, reviewing observations, planning future routes, and building a visual map of where you have paddled.

The app is currently designed as a private journal first. Public sharing is intentionally limited while the product is still being refined.

## Quick Start

1. Go to `https://yourkayakingjournal.com`.
2. Sign in, or create an account if registration is open.
3. Complete the profile setup after creating your login.
4. Add sessions manually from **Add session**, or import Garmin history from **Import**.
5. Use **Dashboard** to review totals, maps, weather summaries, and progress.
6. Use **Library** to find logged sessions, saved plans, and collections.
7. Use **Planning** to sketch future paddles before logging them.

## Main Navigation

The top navigation is the main way to move through the journal.

| Section          | Purpose                                                                                  |
| ---------------- | ---------------------------------------------------------------------------------------- |
| Dashboard        | Overview of logged paddles, metrics, route maps, conditions, expeditions, and progress.  |
| Diary            | Calendar-style view of paddling days and session details.                                |
| Observations     | Notes about lessons learned, mistakes, improvements, and reflections.                    |
| Expedition notes | Expedition-specific notes and world/place map views.                                     |
| Courses          | Placeholder for future learning/course content.                                          |
| Planning         | Plan a future day out with a route map and area forecast.                                |
| Add session      | Create a new logged paddle manually or with files.                                       |
| Library          | Manage planned sessions, logged sessions, and collections/folders.                       |
| Import           | Import Garmin CSV, GPX, and FIT files.                                                   |
| Account          | Profile, map defaults, gear lists, password, security, appearance, and account deletion. |
| Users            | Owner-only product/user insights if your account is configured as an owner.              |

## Account and Profile Setup

Open **Account** to manage your profile.

Use the profile section to set:

- Account name and email.
- Paddler name.
- Kayak club.
- Kayaks owned.
- Paddles owned.
- Default map location.
- Default map zoom.

The default map location is used when placing a manual session and when opening map-based screens before any pins are available. Use the map picker in Account to place the pin visually, then save.

The kayak and paddle lists are used as suggestions when logging a session. They are text tags, not counts. Example kayak entries might be `P&H Scorpio`, `Valley Etain`, or `Nordkapp`. Example paddle entries might be `Werner Cyprus` or `Gearlab Kalleq`.

## Dashboard

The Dashboard is the main overview of your kayaking history.

It includes:

- Total distance.
- Total duration.
- Average speed.
- Average air and sea temperature.
- Beaufort distribution.
- Wind counts.
- Distance comparison windows.
- Sea/tide/current profile.
- Environmental condition summaries.
- Rescue and development events.
- Monthly distance.
- Route map.
- "I paddled here" place map.
- Expedition summary and expedition map.

### Route Map

The route map shows sessions with route data from GPX, FIT, Garmin import, or manually traced routes.

Use it to:

- See all tracked paddles.
- Hover over tracks to highlight them.
- Click a track to open the session.
- Use map filters to focus on routes, places, or expedition sessions.

### "I Paddled Here" Map

This map shows places you have paddled, even if a session is not tagged as an expedition.

Pins are deduplicated where sessions share the same or very close location. The goal is to avoid a noisy pile of repeated pins while still showing your paddling footprint.

### Expedition Map

The expedition map is separate from the general paddled-places map.

It only shows sessions that are tagged as expedition or multiday. Pills below the expedition map should only represent expedition-tagged sessions.

## Add or Edit a Session

Open **Add session** to log a paddle.

The form has four steps:

1. **Journey**: title, date, route type, place, distance, duration, gear, partners, collections, and map location.
2. **Sea**: wind, tide, current, swell, temperature, visibility, and forecast severity.
3. **Rescue and development**: rolls, wet exits, tow rescues, skills, confidence, fatigue, decisions, what went well, and what to improve.
4. **Notes and files**: observations, expedition notes, GPX, FIT, and session photo.

Minimum save requirement:

- Title.
- Date.
- Distance or a GPX/FIT route file.

Launch name is useful, but it is not required. A manually placed pin can be enough to geolocate a session.

### Manual Geolocation

In the Journey step, use **Place the session** to add coordinates.

You can:

- Place a launch pin.
- Place a landing pin.
- Trace route points manually.
- Drag markers to refine the route.
- Double-click a route point to remove it.
- Use **Fit view** to recenter the map manually.

If you only know the general location, add a launch pin. That is enough to make the session appear on the "I paddled here" map.

### Manual Route Tracing

Manual tracing is useful when you did not record a GPX/FIT file but still want a visible route.

Add launch and landing points, then switch to trace route and click along the course. The app stores those points as an editable manual route.

If a session already has a GPX or FIT track, the imported route remains the source of truth. You can still edit session notes, conditions, gear, categories, photos, and metadata.

### Observations

Use the **Observations** field for reflections such as:

- What went wrong.
- What should improve next time.
- Mistakes made.
- Judgment calls.
- Skills to practice.
- Equipment or preparation lessons.

Observations are intentionally different from simple wildlife or scenery notes. The app treats them as learning notes.

### Expedition Tagging

Turn on **Tag as expedition / multiday** when a session belongs to an expedition or multiday trip.

If an expedition-tagged session has GPX/FIT data or saved launch/landing coordinates, it will appear on the expedition map.

If an expedition-tagged session has no route file and no coordinates, it still counts in expedition totals but cannot be placed on the map until a point is added.

### Weather Autofill

If weather autofill is enabled, the app can try to fill marine conditions from the configured weather provider.

Weather autofill can populate fields such as:

- Wind speed.
- Gust.
- Wind direction.
- Beaufort force.
- Tide state.
- Current speed and direction.
- Wave height.
- Swell height, period, and direction.
- Air temperature.
- Sea temperature.
- Visibility.
- Severity ratings.

Beaufort is derived automatically when wind speed is available.

Weather data depends on provider availability, subscription limits, and location coverage. If the weather provider is unavailable, the session can still be saved and filled manually.

## Session Detail Page

Open a session from Dashboard, Diary, Observations, Expedition pages, or Library.

The session detail page shows:

- Core journey information.
- Distance and duration.
- Wind, tide, current, temperature, swell, and sea state.
- Development/rescue notes.
- Observations and expedition notes.
- Route map if route data exists.
- Session photo if uploaded.
- GPX/FIT file references if attached.

Use **Edit session** to update the session.

## Garmin Import

Open **Import** to bring in Garmin history.

You can upload:

- Garmin activities CSV.
- GPX files.
- FIT files.

The CSV creates or updates sessions. GPX and FIT files can be uploaded with the CSV or later by themselves.

### Full Garmin Import

Use this when starting from a Garmin export.

1. Export activities from Garmin as CSV.
2. Open **Import**.
3. Choose the Garmin activities CSV.
4. Optionally add GPX and FIT files at the same time.
5. Toggle weather autofill if available and desired.
6. Submit the import.

### GPX/FIT Repair Import

Use this when sessions already exist but tracks did not attach correctly.

1. Open **Import**.
2. Leave the CSV empty.
3. Upload GPX and/or FIT files.
4. Submit.

The app tries to match files to existing sessions using Garmin timestamps and session dates.

### Weather During Import

If **Fill weather from Stormglass after import** is enabled, imported sessions can be enriched after route data gives the app a timestamp and location.

This can consume provider quota. If quota is exceeded or the API key is invalid, the import may still work while weather enrichment fails.

## Library

Open **Library** to manage saved plans, logged sessions, and collections.

Library is split into:

- Planned sessions.
- Logged sessions.
- Collections / folders.

Each section can be collapsed to reduce clutter.

### Planned Sessions

Saved plans from **Planning** appear here.

Open a plan to continue editing it in the planner.

### Logged Sessions

Logged sessions appear as cards. Open a card to view the session detail page.

Use filters or collection selection to narrow the list.

### Collections / Folders

Collections are a way to group logged sessions that belong together but are not necessarily expeditions.

Examples:

- `Anglesey 2026`.
- `Club paddles`.
- `Winter training`.
- `Navigation practice`.
- `Reykjavik evenings`.

Create a collection from Library, then assign sessions to it.

### Drag-and-Drop Sorting

Library supports a sorting mode for quickly assigning sessions to collections.

Use sorting mode when you want to:

- Select multiple sessions.
- Collapse session cards for easier dragging.
- Drag a group into a collection.

If a session card feels too large while sorting, enable the compact/selection workflow so only the title is prominent.

## Diary

Open **Diary** for a calendar-style view of paddling days.

Use it to:

- Pick a day.
- See sessions logged on that day.
- Review the main details of a selected session.
- Jump into a session detail page.

The diary is best for answering "what did I paddle on this date?" rather than analyzing totals.

## Observations

Open **Observations** to review only sessions with observation text.

Sessions without observation text are hidden from this screen. This keeps the page focused on learning notes rather than every logged paddle.

Use this page to review:

- Recurring mistakes.
- Training themes.
- Judgment patterns.
- Improvements for next time.
- Skills that need practice.

Open a session from Observations to edit or expand the note.

## Expedition Notes and Expedition Atlas

Open **Expedition notes** for expedition-specific review.

This section focuses on:

- Expedition-tagged sessions.
- Multiday paddles.
- Expedition distance.
- Expedition days.
- Places visited on expedition.
- Expedition route/photo summaries.

The expedition atlas is world-facing in layout: it is intended to show pins around the world for expedition-tagged sessions.

Important distinction:

- The general paddled-places map can show any session with a point.
- The expedition map should only show expedition-tagged sessions.

## Planning

Open **Planning** to sketch a future paddle before logging it.

The planning screen is for "day out" preparation, not for recording a completed session.

You can:

- Name a plan.
- Pick a date and time.
- Set estimated speed in knots.
- Add route points on the map.
- Close a loop by returning to the first point when supported.
- See course distance and estimated duration.
- Toggle live weather layers when MapTiler is available.
- Preview an area forecast.
- Save the plan.

Saved plans appear in Library under **Planned sessions**.

### Planning Map

The planning map has two modes:

- Basemap-only mode for drawing a route clearly.
- Live weather mode for animated weather layers.

Use basemap-only mode when you are actively placing route points. Use live weather mode when you want to understand conditions over the area.

Live weather layer controls may include:

- Temperature.
- Precipitation.
- Wind.
- Pressure.
- Radar.
- Transparency.

Weather animations are visual guidance only. They should not replace official marine forecasts, tide tables, local knowledge, or judgment on the day.

### Area Forecast

The planning forecast is area-based rather than waypoint-by-waypoint.

It may show:

- Wind.
- Tide state.
- Current.
- Sea/wave height.
- Swell.
- Temperature.
- Severity.

If swell, sea, tide, or current are missing, the likely causes are provider coverage, provider quota, or the fallback provider not supporting that field for the chosen area/time.

## Settings, Security, and Appearance

Open **Account** for settings.

### Profile

Use this to update paddler identity, club, gear lists, and default map location.

### Security

Use this to:

- Change password.
- Manage two-factor authentication if enabled.
- Delete the account.

Deleting an account removes owned profile data and owned session media.

### Appearance

Use this to choose the app theme.

If your browser or device is in dark mode and contrast looks wrong, switch theme manually from Account. If a specific card remains hard to read, take a screenshot and treat it as a UI bug.

## Owner Tools

Owner tools are only visible to accounts listed in the owner email configuration.

The owner insights screen can show:

- User count.
- Signup activity.
- Profile completion.
- Session activity.
- Garmin import activity.
- Observation activity.

These tools are for operating the private beta, not for normal paddling use.

## Public Sharing

The app currently works private-first.

Public profile routes exist in the codebase, but public profiles are disabled by default for the private launch. Decide later what should be exposed publicly as read-only.

Until public sharing is deliberately enabled:

- Treat sessions as private.
- Do not assume friends can see your logbook unless they have an account and access.
- Use screenshots or a temporary account for private feedback.

## Data and Files

The app stores:

- Account data.
- Profile settings.
- Logged sessions.
- Planned sessions.
- Observations.
- Expedition notes.
- Categories/collections.
- Route points and route summaries.
- Uploaded GPX files.
- Uploaded FIT files.
- Uploaded session photos.

Media storage should be private. Uploaded files should not be publicly readable unless public sharing is explicitly designed and enabled later.

## Recommended Workflows

### Log a Manual Session With a Pin

1. Open **Add session**.
2. Fill title and date.
3. Add distance if known.
4. Open the Journey map.
5. Place a launch pin.
6. Add duration, gear, partners, and collection if useful.
7. Add weather manually or use autofill if available.
8. Add observations in Notes and files.
9. Save.

### Add an Observation to a Garmin-Imported Session

1. Open the imported session.
2. Click **Edit session**.
3. Go to **Notes and files**.
4. Add text under **Observations**.
5. Click **Update session**.

You do not need to re-upload GPX or FIT files just to add an observation.

### Group Sessions Into a Collection

1. Open **Library**.
2. Create a collection, such as `Anglesey 2026`.
3. Enable sorting mode if you want to move several sessions quickly.
4. Select one or more logged sessions.
5. Drag or assign them to the collection.
6. Collapse sections as needed to keep the page tidy.

### Plan a Future Paddle

1. Open **Planning**.
2. Name the plan.
3. Set the date, start time, and estimated speed.
4. Use basemap mode to place route points.
5. Review distance and estimated duration.
6. Toggle live weather if needed.
7. Review the area forecast.
8. Save the plan.
9. Find it later in Library under Planned sessions.

### Import Garmin History From Scratch

1. Prepare the Garmin CSV export.
2. Prepare matching GPX/FIT files if available.
3. Open **Import**.
4. Upload the CSV.
5. Upload GPX/FIT files if available.
6. Decide whether to enable weather autofill.
7. Submit.
8. Review the Library and Dashboard maps.

## Troubleshooting

### "Update session" does nothing

Refresh the page after a deploy and try again.

If it still appears stuck:

- Check whether a red **Session not saved** banner appears.
- Check the Journey step for validation errors.
- Make sure title and date are present.
- Make sure the session has either a distance or an existing GPX/FIT route file.
- Try saving without uploading a new file.

### A session does not appear on the main route map

The main route map needs route data.

Add one of:

- GPX file.
- FIT file.
- Manual traced route.

A simple pin is enough for "I paddled here", but not enough for a full track line.

### A session does not appear on "I paddled here"

The session needs at least one usable coordinate.

Add one of:

- Launch pin.
- Landing pin.
- GPX route.
- FIT route.
- Manual route.

### An expedition does not appear on the expedition map

Check both:

- The session is tagged as expedition / multiday.
- The session has route data or a saved coordinate.

If the tag is missing, it will not appear on the expedition map even if it appears on the general map.

### Weather did not fill

Possible causes:

- Weather provider API key is missing or invalid.
- Provider quota is exceeded.
- The area or time has incomplete marine coverage.
- No route point or launch/landing coordinate exists.
- The provider does not support the requested field.

You can still save the session and fill conditions manually.

### Tide, current, sea, or swell is missing

Marine fields depend heavily on provider coverage.

Open-Meteo Marine and Stormglass may not always return every field for every location/time. If a field is missing, it is safer to show it as missing than to invent it.

### Dark mode contrast looks wrong

Open **Account** and choose a manual appearance theme rather than relying on browser/system theme.

If a specific card is unreadable, capture a screenshot and log it as a UI refinement.

### Planning weather animation is hard to read

Try:

- Switching to basemap-only mode while drawing.
- Increasing weather layer opacity.
- Zooming closer to the area.
- Changing the active weather layer to wind or precipitation.
- Using the area forecast card for numeric guidance.

### Garmin GPX/FIT did not match existing sessions

Try importing the GPX/FIT files alone from **Import**.

The app matches using timestamps and dates. If a file timestamp is missing, shifted, or does not correspond to a logged session, it may not match automatically.

## Safety Notes

Planning and forecast features are decision-support tools only.

Always verify with appropriate official and local sources before paddling:

- Marine forecast.
- Tide tables.
- Local current information.
- Harbour/local notices.
- Wind and swell observations.
- Personal skill, group ability, and bailout options.

Do not use this app as the only source for navigation or go/no-go decisions.

## Glossary

| Term            | Meaning                                                                                   |
| --------------- | ----------------------------------------------------------------------------------------- |
| Session         | A completed logged paddle.                                                                |
| Planned session | A future route or idea saved from Planning.                                               |
| Observation     | A reflective note about learning, mistakes, improvements, or judgment.                    |
| Expedition      | A session tagged as expedition or multiday.                                               |
| Collection      | A folder-style grouping for sessions, such as a trip, club paddle set, or training theme. |
| GPX             | GPS track file format commonly exported from Garmin and mapping tools.                    |
| FIT             | Garmin activity file format that can include route, time, speed, and sensor data.         |
| Beaufort        | Wind force scale derived from wind speed when available.                                  |
| Launch pin      | Coordinate where the paddle started.                                                      |
| Landing pin     | Coordinate where the paddle ended.                                                        |
| Manual route    | Editable route created by clicking points on the map.                                     |
