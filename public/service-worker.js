// Stable root entry point: keeps the PWA scope at / while the generated worker
// remains in Vite's /build directory.
// Change this release marker with each forced PWA recovery release so browsers
// reliably fetch the new worker instead of retaining an outdated app shell.
importScripts('/build/service-worker.js?release=20260820-push-onboarding');
