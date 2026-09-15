# ARA CMS V20.9.7 — Contact Form + Contact Email Settings

## Fix
- Fixed `Fatal error: Call to undefined function setting()` in `public/contact.php` by loading `app/Content.php` before using `setting()`.
- Contact form messages continue to be stored in the admin Messages table even if SMTP delivery fails.

## Contact recipient setting
- Added **Admin → Contact Form**.
- The owner can configure the email address that receives website contact inquiries.
- Existing Visual Builder → Site Settings → Email Kontak remains compatible because it writes the same `contact_email` setting.
- If the recipient is left empty, the SMTP `From Email` is used as a fallback.

## SMTP
- V20.9.6 SMTP greeting/STARTTLS fix is preserved unchanged.
- Contact mail uses the configured SMTP transport and visitor email as `Reply-To`.

## Fresh install
- Added `contact_email` to `database/schema.sql` defaults.
- Existing installations use the existing lazy settings registry in `app/Content.php`.
