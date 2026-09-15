# ARA CMS V20.9.9 — Session Flash Header Fix

## Fix
- Memulai secure session di `public/index.php` sebelum `render_site()` menghasilkan HTML.
- Mencegah warning `session_name(): Session name cannot be changed after headers have already been sent`.
- Mencegah warning `session_start(): Session cannot be started after headers have already been sent`.
- Contact success flash tetap bisa dibaca oleh halaman live setelah redirect dari `contact.php`.

## Root cause
V20.9.8 menampilkan notifikasi sukses contact melalui session flash di dalam `app/site-template.php`. Saat blok tersebut dipanggil, sebagian HTML halaman sudah dikirim ke browser sehingga `session_name()`/`session_start()` terlalu terlambat.

## Compatibility
Tidak mengubah alur SMTP, auto-reply, contact form, atau konfigurasi email V20.9.8.
