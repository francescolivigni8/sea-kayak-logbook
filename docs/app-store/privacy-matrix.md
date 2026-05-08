# App Privacy Matrix

This is the working draft for Apple App Privacy answers. Final answers should be checked in App Store Connect against the live production behavior.

## Likely Data Categories

### Contact Info

- Email address
- Name

Purpose:
- account creation
- login
- password recovery
- support/owner administration

### User Content

- session notes
- expedition notes
- uploaded GPX/FIT files
- uploaded photos
- planned routes
- feedback submissions

Purpose:
- core app functionality
- user-requested export/reporting

### Precise Location / Location-Derived Content

The app stores user-entered or imported route/location points and planning/session map data.

Purpose:
- route display
- planning
- launch/landing/session context

### Diagnostics

If Sentry is enabled in production, error telemetry may be collected for crash/exception monitoring.

Purpose:
- app stability
- debugging

## Questions To Confirm Before Submission

- Is PostHog still disabled for the iOS launch?
- Are public profiles still disabled?
- Is any data used for tracking across third-party apps or websites? The expected answer should be **no**.
- Are uploads always user-initiated? The expected answer should be **yes**.
