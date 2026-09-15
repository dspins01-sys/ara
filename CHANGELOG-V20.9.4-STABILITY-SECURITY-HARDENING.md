# Ara CMS V20.9.4 — Stability & Security Hardening

Base: V20.9.3.1 Builder Sidebar Collapse Fix

## Changes

- Added centralized `ara_safe_url()` URL sanitizer.
  - Allows `http`, `https`, `mailto`, `tel`, anchors, and normal relative paths.
  - Rejects executable/dangerous schemes such as `javascript:`, `data:`, `vbscript:`, `file:`, and `blob:`.
  - Rejects protocol-relative URLs (`//...`) so user-controlled links cannot bypass the intended scheme policy.
- Applied URL sanitization to section button URLs, navigation URLs, Site Settings canonical/social URLs, custom template button URLs/settings, and live-site social links.
- Made contact-form redirect subdirectory-safe by using `ara_app_base_path()` instead of redirecting to the domain root.
- Added the install guard to the direct contact endpoint so an uninstalled CMS cannot hit the message handler before schema initialization.
- Expanded template-layout restore to include all header design settings: logo height, header background, header height mode, header height, and header vertical padding.
- Template-layout restore now also restores per-section typography from the revision snapshot.
- Added explicit `admin/.htaccess` with `DirectoryIndex index.php` for Apache directory requests.

## Validation

- All PHP files pass `php -l`.
- Builder/live-site JavaScript files pass Node syntax checks.
- URL sanitizer regression checks cover allowed URLs and dangerous scheme variants.
- ZIP/package integrity checked after build.

## Scope

This is a stability/security hardening release. Full Undo/Redo and richer revision UX remain planned for a later builder release rather than being mixed into this patch.
