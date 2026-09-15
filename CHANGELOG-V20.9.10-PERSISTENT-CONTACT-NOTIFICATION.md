# ARA CMS V20.9.10 — Persistent Contact Success Notification

## Changes
- Contact success notification no longer auto-clears on the first page render.
- Notification remains visible after refresh while the session flash is still present.
- Added a manual `×` close button.
- Closing the notification clears the session flash through `?contact=dismiss#contact`.
- Existing SMTP, contact recipient, and custom auto-reply behavior is unchanged.
- Keeps the V20.9.9 early-session-start fix to avoid `session_name()` / `session_start()` header warnings.

## Validation
- Updated `app/site-template.php`.
- Updated `public/assets/css/site.css`.
- PHP syntax check passed for the modified PHP file.
