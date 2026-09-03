# Ara CMS V20.8.3 — Mobile Preview Viewport + Full Builder Topbar

## Fix
- Builder topbar tetap full-width saat mode Mobile.
- Website preview kembali memakai viewport mobile 390px.
- Mobile responsive rules tetap aktif melalui `html.ce-mobile-preview`.
- Desktop dan Tablet tidak diubah.
- Tidak ada perubahan database/persistence/live-site content.

## Root cause
V20.8.2 membuat topbar full-width tetapi canvas website juga ikut full-width. Akibatnya layout preview terlihat seperti Desktop. V20.8.3 memisahkan kedua layer: Builder chrome full-width, website body 390px.
