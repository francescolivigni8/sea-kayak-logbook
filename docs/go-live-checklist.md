# Web Go-Live Checklist

Last updated: 8 May 2026

This is the practical launch checklist for **Your Kayaking Journal** as a web app.

It is split into:

- **Must do before broader web launch**
- **Should do this week**
- **Can wait until after launch**

The goal is to separate what is truly blocking public trust from what is simply product polish.

---

## Launch Target

Current honest position:

- **Invite-only / tester launch**: close and acceptable once the must-do items below are checked off.
- **Open public web launch**: not yet ideal until the must-do items are complete.

This checklist is for the broader public web launch standard, not just a private tester round.

---

## Must Do Before Broader Web Launch

### 1. Clean release verification pass

- [ ] Run a full local verification pass with:
  - `npm run verify`
- [ ] Confirm the command completes without hanging and without failures.
- [ ] If the local full verify still feels unreliable, use GitHub Actions as the final release truth source before shipping.

Notes:

- The repo now has wrapped heartbeat-based commands for `types`, `build`, and `verify`.
- The goal is not just “it usually works”, but one boring, complete green run before release.

### 2. Confirm recent production migrations ran

- [ ] Open the latest Laravel Cloud deployment logs.
- [ ] Confirm the legal acceptance migration ran:
  - `2026_05_02_120000_add_legal_acceptance_columns_to_users_table`
- [ ] Confirm the feedback inbox migration ran:
  - `2026_05_08_120000_create_feedback_reports_table`
- [ ] If needed, run:
  - `php artisan migrate:status`

Why it matters:

- The app now depends on legal acceptance tracking and feedback-report persistence.
- If code is live but these tables/columns are missing, parts of the app will fail in production.

### 3. Prove password reset email works on live

- [ ] Use the real live domain.
- [ ] Trigger “Forgot password”.
- [ ] Confirm the email arrives.
- [ ] Confirm the reset link opens the correct host from `APP_URL`.
- [ ] Confirm the reset completes successfully.

Required env outcome:

- real mail provider configured
- sender domain verified
- SPF/DKIM valid

This is a launch blocker because account recovery is not optional once real users exist.

### 4. Run a full production smoke test

- [ ] Register a new user
- [ ] Complete profile setup
- [ ] Log out and back in and confirm redirect to `/dashboard`
- [ ] Submit the legal acceptance flow if prompted
- [ ] Create a **Quick** session
- [ ] Create an **Extended** session
- [ ] Create a manual route trace and confirm distance is derived from the trace
- [ ] Edit a session and confirm save/update works
- [ ] Save a planned route
- [ ] Export a planned route GPX
- [ ] Import Garmin CSV + GPX/FIT
- [ ] Confirm at least one recent Garmin import shows correct totals
- [ ] Use weather autofill where available
- [ ] Upload a session photo
- [ ] Upload GPX/FIT
- [ ] Submit a feedback report
- [ ] Confirm the owner can read it in `/insights/feedback`
- [ ] Generate a session share / PDF page
- [ ] Generate the account report
- [ ] Download account export / backup

Minimum bar:

- no broken flows
- no silent “nothing happens” buttons
- no missing pages
- no obvious production-only regressions

### 5. Confirm monitoring is watching the app

- [ ] Sentry is enabled and pointed at the production environment
- [ ] Test exception reporting has been confirmed at least once
- [ ] `/health` is monitored externally
- [ ] Deploy-failure notifications are enabled in Laravel Cloud

Minimum bar:

- if login breaks, imports break, or exceptions spike, we find out quickly

### 6. Confirm private media works correctly

- [ ] Upload a photo
- [ ] Upload a GPX
- [ ] Upload a FIT file
- [ ] Confirm files are accessible while authenticated
- [ ] Confirm storage is private, not public-by-accident
- [ ] Confirm account deletion removes related uploaded media

This matters because the app handles user-uploaded routes and photos, not just text records.

### 7. Make a deliberate access decision

- [ ] Decide whether launch remains **invite-only**
- [ ] Or whether registration is open to anyone
- [ ] If invite-only, confirm the allowed-user path is intentional and documented
- [ ] If open registration, confirm mail, recovery, abuse handling, and support are ready enough

Do not “accidentally” become public just because the app is online.

---

## Should Do This Week

These are not hard blockers for a careful beta-to-public step, but they are important quality/trust items.

### 1. Improve Garmin activity mapping

- [ ] Support legacy Garmin activities logged as `Running` when they are clearly paddling history
- [ ] Include `Whitewater`
- [ ] Include `SUP`
- [ ] Decide whether remapping should happen:
  - at import time
  - through an owner/user import rule
  - or with post-import recategorization tools

This is one of the biggest current trust gaps for paddlers with long real histories.

### 2. Strengthen weather trust messaging

- [ ] Keep the improved planning confidence logic
- [ ] Continue reducing “model gust panic” behavior
- [ ] Make forecast source / agreement / confidence more obvious where needed
- [ ] Ensure planning clearly reads as guidance, not navigation authority

### 3. Finish a support rhythm

- [ ] Decide how often owner reviews `/insights/feedback`
- [ ] Define what counts as:
  - bug
  - feature request
  - launch blocker
- [ ] Decide where resolved tester issues are tracked

### 4. Confirm backups beyond “we have backups”

- [ ] Confirm Laravel Cloud database backup status
- [ ] Confirm object-storage retention / recovery plan
- [ ] Keep the Git backup workflow active
- [ ] Verify local export/backup package still works from the account page

### 5. Tidy live copy and dormant UI

- [ ] Keep hidden/paused public sections truly out of the main flow
- [ ] Check navigation labels after recent cleanup passes
- [ ] Remove any remaining wording that feels internal, transitional, or prototype-like

---

## Can Wait Until After Launch

These matter, but they should not delay a controlled web launch if the must-do items are done.

### 1. Restore/import from backup

- [ ] Add a real restore flow for JSON / backup package

Today we support export and backup well enough, but restore is still manual/nonexistent.

### 2. Broader performance work

- [ ] Profile dashboard-heavy pages for larger accounts
- [ ] Watch route/map-heavy sessions
- [ ] Reduce payload size where needed

### 3. App Store / native packaging

- [ ] Wrap with Capacitor or equivalent
- [ ] Prepare icons, privacy labels, bundle IDs, review assets

This is a separate project from “good mobile web launch”.

### 4. Public read-only relaunch

- [ ] Decide exactly what should be public
- [ ] Revisit `KAYAK_PUBLIC_PROFILES_ENABLED`
- [ ] Re-run legal/privacy expectations for public exposure

### 5. Advanced product admin

- [ ] richer owner inbox workflow
- [ ] status changes for feedback reports
- [ ] export of feedback
- [ ] better issue triage tooling

---

## Suggested Launch Decision Rule

Launch more broadly only when all of these are true:

- [ ] full verify lane is green
- [ ] live migrations are confirmed
- [ ] password reset works on live
- [ ] production smoke test is complete
- [ ] monitoring is active
- [ ] storage/privacy behavior is confirmed
- [ ] access policy is deliberate

If any one of those is still uncertain, stay in controlled/private launch mode.

---

## Immediate Next Run

When we resume launch work, do these in order:

1. Run `npm run verify`
2. Check Laravel Cloud migration status
3. Test password reset on the live domain
4. Run the production smoke test
5. Check Sentry and health monitoring
6. Decide invite-only vs open registration

