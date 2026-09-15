# ARA CMS V20.9.8 — Contact Notification + Custom Auto-Reply

## Fixes
- Contact form now shows a success notification after a submission is stored.
- Contact form continues redirecting back to `#contact` after submit.
- Added configurable contact recipient and custom auto-reply settings.
- Auto-reply can be enabled/disabled independently.
- Auto-reply subject and HTML body are editable from Admin > Contact Form.
- Supported placeholders: `{name}`, `{email}`, `{message}`, `{site_name}`.
- Visitor email is used as Reply-To for the owner notification.
- Auto-reply is sent from the configured SMTP From Email to the visitor.
- SMTP failures are logged without losing the stored contact message.
- Preserves V20.9.6 SMTP greeting/TLS/authentication fix.
