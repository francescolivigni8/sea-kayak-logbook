# TestFlight Checklist

Use this checklist after the iOS project is created and opens in Xcode.

## Native Build Basics

- [ ] App opens without white-screen or crash
- [ ] Status bar looks intentional
- [ ] Splash/launch look intentional
- [ ] App icon is correct on device
- [ ] Bundle ID and signing are correct

## Authentication

- [ ] Login works
- [ ] Logout works
- [ ] Session survives app relaunch
- [ ] Password reset email works from the app flow
- [ ] Legal acceptance flow works if triggered

## Core Product Flows

- [ ] Quick session can be created
- [ ] Extended session can be created
- [ ] Planning route can be saved
- [ ] Garmin import page loads cleanly
- [ ] Feedback form submits
- [ ] Owner feedback inbox loads for owner accounts

## File And Sharing Flows

- [ ] GPX upload works
- [ ] FIT upload works
- [ ] Photo upload works
- [ ] Session share / PDF view opens correctly
- [ ] Course report opens correctly
- [ ] Native share sheet behaves correctly where expected

## Map And Mobile UX

- [ ] Planning map is responsive on iPhone
- [ ] Session location picker works reliably
- [ ] Keyboard does not cover critical inputs
- [ ] No horizontal overflow on primary mobile flows

## Release Readiness

- [ ] At least one internal TestFlight run completed
- [ ] At least two external testers used the app on real iPhones
- [ ] Any WKWebView-specific bugs have been fixed
- [ ] App Store screenshots are captured from the native build
