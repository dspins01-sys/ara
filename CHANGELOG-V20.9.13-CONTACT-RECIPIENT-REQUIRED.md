# ARA CMS V20.9.13 — Contact Recipient Required

- Removed `contact_fallback_email` from the Contact Form settings/defaults.
- `contact_email` is now mandatory in Admin → Contact Form.
- Server-side validation rejects an empty or invalid Contact Form recipient.
- Public Contact Form uses only the configured `contact_email`; SMTP `from_email` is never used as a recipient fallback.
- If an existing installation still has a legacy `contact_fallback_email` setting, it is ignored and cleared when Contact Form settings are saved.
- Preserves V20.9.10 persistent notification, V20.9.11 header redirect fix, V20.9.9 session fix, SMTP, and auto-reply behavior.
