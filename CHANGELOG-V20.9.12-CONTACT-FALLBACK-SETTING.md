# ARA CMS V20.9.12 — Configurable Contact Fallback

- Added `contact_fallback_email` site setting.
- Admin → Contact Form now has a configurable Default Fallback Contact.
- Recipient priority: Contact Form recipient → Default Fallback Contact → SMTP From Email (last-resort compatibility fallback).
- Added validation for fallback email.
- Preserves V20.9.11 header/redirect fix and all previous contact, session, SMTP, and auto-reply fixes.
