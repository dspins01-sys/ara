# Ara CMS V20.9.4.5 — Live Arrow & Dynamic Block Fix

## Fixes

### 1. Live Back-to-Top arrow matches Builder indicator
- Same 38px circular footprint on desktop.
- Same dark background, border, shadow, typography, and right-side placement style.
- Uses the same `➜` glyph as the Builder active-block indicator.
- Mobile scales to 32px, matching the Builder mobile indicator.

### 2. Newly inserted blocks are immediately editable
- Root elements passed as a scoped DOM node are now included in editor binding queries.
- Fixes the issue where a newly added/duplicated block appeared visually but its edit toolbar/content controls only became available after refresh.
- Applies to editable text, href controls, images, and block toolbars.

## Validation
- PHP syntax check: clean.
- Builder JavaScript syntax check: clean.
- ZIP integrity: clean.
