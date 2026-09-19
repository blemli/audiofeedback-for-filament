# Changelog

All notable changes to `audiofeedback-for-filament` will be documented in this file.

## v1.4.0 - 2026-09-19

### Changed

- **Package renamed to `blemli/audiofeedback-for-filament`** (was `blemli/filament-audiofeedback`). Namespace, plugin class, config key, translation namespace, routes and the `audiofeedback_settings` table are unchanged. To migrate: `composer remove blemli/filament-audiofeedback && composer require blemli/audiofeedback-for-filament`, then `php artisan filament:assets` if you publish assets — they now live under `public/{js,css}/blemli/audiofeedback-for-filament/` (delete the old `blemli/filament-audiofeedback` directories).
- The install command no longer asks you to star the repository.

### Fixed

- The uninstall command now also removes the published stylesheet directory.

**Full changelog**: https://github.com/blemli/audiofeedback-for-filament/compare/v1.3.2...v1.4.0

## v1.3.2 - 2026-09-17

### Fixed

- **Mute button on the sidebar axis** — when the user menu lives in the sidebar (no topbar), the `user-menu-before` toggle rendered as a topbar icon button and sat off-axis in the collapsed rail. It now takes the shape of Filament's own bell trigger (full width, centred icon, label only while the sidebar is open) via a small stylesheet the package registers.

**Full changelog**: https://github.com/blemli/audiofeedback-for-filament/compare/v1.3.1...v1.3.2

## v1.3.1 - 2026-09-17

### Fixed

- **Keyboard-triggered cues no longer wait for the next click** ⌨️ — Safari starts audio only inside a gesture handler and Firefox only after one; a cue arriving after a Livewire roundtrip (⌘S → save → notification) sat on the suspended context until the next click. Every keydown and pointerdown now wakes both engines while the gesture runs (the Cuelume one through an inaudible tick). Cuelume 0.2.2.

**Full changelog**: https://github.com/blemli/audiofeedback-for-filament/compare/v1.3.0...v1.3.1

## v1.3.0 - 2026-09-17

### What's new

- **Your own samples** 🎚️ — `->customSound('shutter', asset('audio/shutter.mp3'))` (or `custom_sounds` in the config) registers an audio file under a name that works everywhere a Cuelume cue does: `sounds`, `->sound()`, the per-user profile selects and `Notification::make()->sound()`. Fetched once, decoded with the Web Audio API, played through the same volume and mute controls.

**Full changelog**: https://github.com/blemli/audiofeedback-for-filament/compare/v1.2.0...v1.3.0

## v1.2.0 - 2026-07-28

### What's new

- **Native Filament profile section** 🎛️ — the Breezy "Sounds" section is now a fully native Filament form: `Toggle`, `Slider` (with a pip marking the panel default and a Reset hint action) and per-event `Select`s in a responsive grid, saving server-side on every change with instant sound previews.
- **Duplicate-tune warnings** — selects show a warning icon (message in a tooltip) when two events resolve to the same tune.
- **Per-user guard** — `->breezyProfileSection(fn (?User $user) => ...)` gates the section per user via Breezy's `canView()`.
- **Reduced motion** ♿ — users with the OS-level "reduce motion" preference start muted; an explicit unmute or saved setting wins, and `->ignoreReducedMotion()` disables the hint.
- **Translations** 🌍 — German, French, Italian and Spanish ship alongside English.
- CI now runs the supported Laravel 12/13 matrix and is fully green.

**Full changelog**: https://github.com/blemli/audiofeedback-for-filament/compare/v1.1.0...v1.2.0

## 1.1.0 - 2026-07-28

- New `delete` event (default: `droplet`): delete and force-delete actions — including bulk — play it through their success notification instead of the generic success chime
- New `Notification::make()->soundEvent('...')` macro to play a configured event's sound (respecting config, fluent and per-user overrides) on any notification

## 1.0.0 - 2026-07-28

Initial release.

- Automatic sounds for notifications (by status), toggles, toggle buttons, sliders, form submits, login/logout, sortable drag & drop, and navigation hover — all mapped to the [Cuelume](https://cuelume-site.pages.dev) cue designed for that moment, and each remappable or mutable via config or fluent plugin calls
- `Notification::make()->sound('sparkle')` / `->silent()` per-notification overrides
- Master volume (`->volume(0–100)`), configurable per panel
- Opt-out mute button, positionable in the topbar or user menu
- Opt-in per-user "Sounds" section for Filament Breezy's my-profile page, with volume slider (default marker + reset), per-event sound picker with instant preview, and a mute switch synced with the topbar button
- Per-user persistence in the `audiofeedback_settings` table with a `localStorage` fallback for guests
- Works with Livewire events, redirects and SPA mode; no Filament views overridden
