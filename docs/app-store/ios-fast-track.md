# iOS Fast Track

This project is now prepared for a **Capacitor-based iOS wrapper** around the live web app at [yourkayakingjournal.com](https://yourkayakingjournal.com).

## Current Architecture

- **Backend**: Laravel 13
- **App shell**: Inertia + Vue 3 + Vite
- **Native wrapper target**: Capacitor iOS
- **Initial native runtime**: remote HTTPS shell pointing at the production domain

This is the fastest path to **TestFlight** from the current codebase.

## What Is Already Done In The Repo

- Capacitor packages are installed in `package.json`
- Shared Capacitor config lives in [capacitor.config.ts](/Users/francesco/Documents/New%20project/sea-kayak-logbook-laravel/capacitor.config.ts)
- iOS helper scripts are available:
  - `npm run ios:check`
  - `npm run ios:assets`
  - `npm run ios:add`
  - `npm run ios:sync`
  - `npm run ios:open`
  - `npm run mobile:sync`
- App Store/TestFlight docs live in [docs/app-store](/Users/francesco/Documents/New%20project/sea-kayak-logbook-laravel/docs/app-store)

## Blocking Machine Requirement

This Mac still needs:

1. **Full Xcode** installed from the Mac App Store
2. `xcode-select` pointed at Xcode, not Command Line Tools
3. ideally CocoaPods installed, unless you choose to migrate the generated project to Swift Package Manager later

## Exact Next Commands Once Xcode Is Installed

1. Check the native toolchain:

```bash
npm run ios:check
```

2. Generate iOS icon/source assets:

```bash
npm run ios:assets
```

3. Create the native project:

```bash
npm run ios:add
```

4. Sync the current web/native config:

```bash
npm run mobile:sync
```

5. Open in Xcode:

```bash
npm run ios:open
```

## After The Xcode Project Opens

1. Set the Apple Team and signing for the `App` target
2. Confirm bundle ID: `com.francescolivigni.yourkayakingjournal`
3. Replace default icon slots with the generated sources from [resources/native/ios](/Users/francesco/Documents/New%20project/sea-kayak-logbook-laravel/resources/native/ios)
4. Confirm the launch/splash background and status bar styling
5. Build to a real iPhone
6. Test login, planning, imports, uploads, sharing, and reports
7. Archive and upload to TestFlight

## Important Product Risk

This first iOS path is a **native shell around the live web app**. It is the fastest path, but it is also the part most likely to need iteration during TestFlight because Apple can be stricter about apps that feel like thin wrappers.

That means TestFlight should be treated as a required stabilization step, not a formality.
