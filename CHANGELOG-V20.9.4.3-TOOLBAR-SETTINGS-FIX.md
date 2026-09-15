# Ara CMS V20.9.4.3 — Toolbar Site Settings Fix

## Fix
V20.9.4.2 hid `Site Settings` and `Lihat Situs` together with the duplicate mobile Header/Slider/Template actions on desktop.

This made the Site Settings action disappear from the desktop builder toolbar, so clicking the expected area could hit underlying live-site content such as social edit controls.

## Changes
- Desktop hides only duplicate `Header`, `Slider`, and `Template` actions from the mobile More menu.
- `Site Settings` remains visible in the desktop toolbar.
- `Lihat Situs` remains visible in the desktop toolbar.
- Mobile More menu behavior remains unchanged.
