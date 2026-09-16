# V20.9.15 — WhatsApp API Notification

- Added optional WhatsApp notification for Contact Form submissions.
- WhatsApp remains a completely separate standalone service; CMS only calls its HTTP API.
- Added Contact Form settings for WA Service URL, API key, account ID, admin phone and message template.
- Added `Test WhatsApp` action in Admin > Contact Form.
- Contact submissions remain stored and email handling remains independent if WhatsApp is unavailable.
- Uses `Authorization: Bearer <WA_API_KEY>` and `POST /api/v1/messages`.
