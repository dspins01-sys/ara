# ARA CMS V20.9.6 — SMTP Greeting / STARTTLS Fix

## Fix
- SMTP client now reads the server's initial `220` greeting immediately after opening the socket.
- Prevents the initial Gmail greeting (`220 smtp.gmail.com`) from being consumed as the response to `EHLO`.
- Prevents the subsequent multiline `250-... STARTTLS ...` EHLO response from being incorrectly interpreted as the response to `STARTTLS`.
- EHLO response is now validated before continuing.
- Existing flash notification fix from V20.9.5 is preserved.

## Gmail
- Host: `smtp.gmail.com`
- Port: `587`
- Encryption: `STARTTLS`
- Username: Gmail address
- Password: Google App Password 16 digit
- For first test, use the same Gmail address as `From Email`.
