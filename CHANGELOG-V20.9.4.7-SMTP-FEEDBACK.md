# ARA CMS V20.9.4.7 — SMTP & Save Feedback

- Save SMTP now shows an explicit success notification after redirect.
- Invalid test recipient shows an explicit error notification.
- SMTP test now clearly reports BERHASIL or GAGAL and includes the recipient address.
- Test Email explicitly uses the saved SMTP configuration.
- Flash messages now support success/error/info/warning presentation.
- No SMTP credentials are exposed in the notification.


## V20.9.4.8 — SMTP feedback actually visible
- Fixed SMTP save/test feedback so it is rendered in the same POST response instead of relying on session flash + redirect.
- Added a prominent success/error result panel at the top of the SMTP page.
- Save action refreshes the SMTP values from the database.
- Test Email now visibly reports both success and the exact SMTP failure message returned by the mailer.
