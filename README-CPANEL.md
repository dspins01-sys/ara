# Ara CMS — cPanel Deployment

## Requirements
- PHP 8.1+ (8.2/8.3 recommended)
- SQLite3 + PDO SQLite enabled
- FileInfo extension enabled
- OpenSSL enabled
- PHP mbstring recommended
- Apache/LiteSpeed with .htaccess support

## 1. Upload
Upload `ara-cms` to your hosting, for example:
`/home/USERNAME/ara-cms`

## 2. Set document root
This project's `.htaccess` is built for **document root = the project root itself** (`/home/USERNAME/ara-cms`), NOT the `public` folder. The root `.htaccess` uses mod_rewrite to serve `public/index.php` for the homepage while still exposing `/admin/`, `/assets/`, and `/uploads/` at clean URLs.

`app/`, `config/`, and `database/` each have their own `.htaccess` (`Require all denied`) so they stay blocked from direct browser access even though they sit inside the document root — you don't need to move them anywhere.

If your host's root domain is already `/home/USERNAME/public_html`, upload the contents of `ara-cms` directly into `public_html` (not into a `public_html/ara-cms` subfolder), so `.htaccess`, `admin/`, `app/`, `public/`, etc. all sit at the domain root.

## 3. Permissions
Make sure these folders are writable by PHP:
- `data/` => 755 (use 775 only if your host requires it)
- `public/uploads/` => 755 (use 775 only if required)

## 4. Install
Open:
`https://your-domain.com/install.php`

Create the first admin account. After a successful install, the installer automatically creates `data/.installed`, removes `install.php` and the legacy `public/install.php` wrapper, then redirects to the website. If the hosting provider blocks file deletion, the installation lock still prevents the installer from being reused.

## 5. Login
`https://your-domain.com/admin/login.php`

## 6. SMTP
Open Admin > SMTP.
Typical examples:
- cPanel mailbox: host `mail.yourdomain.com`, port `465` + SSL or `587` + STARTTLS
- Gmail: host `smtp.gmail.com`, port `587` + STARTTLS; use an App Password, not the normal account password

Use Admin > SMTP > Send Test to verify.

## 7. Contact form
Messages are always stored in SQLite first. If SMTP is configured, the CMS also attempts to email the configured contact address.

## 8. Composer / PHPMailer
The starter contains `composer.json`, but the current lightweight SMTP adapter does not require Composer. If you want PHPMailer later, run `composer install` and replace the adapter in `app/Mailer.php`.

## 9. Production checklist
- HTTPS enabled
- `install.php` automatically deleted after successful installation
- `data/` outside document root
- `public/uploads/` writable but PHP execution blocked by `.htaccess`
- strong admin password
- regular backup of `data/cms.sqlite`
- SMTP credentials kept private


## SMTP fix
The SMTP admin page explicitly loads `app/Mailer.php`, which defines `smtp_settings()` and `send_smtp()`. The password field is blank by default and leaving it blank preserves the existing password.

## Live Preview & Ara-style Template

Versi ini memakai template frontend yang mengikuti screenshot PT Ara DigiTalent yang diberikan: header hitam, hero cream dengan gambar lebar, section dua kolom, section Tentang berwarna cream, beberapa feature section putih, lalu Contact/Footer gelap.

Admin > Content sekarang menyediakan editor text dan nama file gambar. Live Preview memakai `admin/preview.php` dan menerima perubahan melalui `postMessage`, jadi perubahan text/image tidak perlu disimpan dulu untuk melihat hasilnya.

### Image slots

- `hero_image`
- `contractor_image`
- `digital_image`
- `consulting_image`

Upload/ganti gambar melalui Admin > Media. File akan masuk ke `public/uploads/`.

### Important for an existing database

Jangan hapus `data/cms.sqlite`. `app/Content.php` otomatis membuat setting baru yang belum ada saat halaman CMS dibuka.


## Document root
For a normal cPanel domain pointed at the project root, the included `.htaccess` routes `/` to `public/index.php` and maps `/assets`, `/uploads`, and `/contact.php` to the public directory. If you instead set the domain Document Root directly to `/public`, do not use the root URL mapping assumptions above.


## XAMPP VHost for ara.kodehijau.com

Use this VirtualHost when the project lives at `C:/xampp/htdocs/ara`:

```apache
<VirtualHost *:80>
    ServerName ara.kodehijau.com
    ServerAdmin webmaster@ara.kodehijau.com

    DocumentRoot "C:/xampp/htdocs/ara"

    <Directory "C:/xampp/htdocs/ara">
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    DirectoryIndex public/index.php

    ErrorLog "logs/ara.kodehijau.com-error.log"
    CustomLog "logs/ara.kodehijau.com-access.log" common
</VirtualHost>
```

Do not use `<Directory "C:/xampp/htdocs/itop">` for the Ara VirtualHost. After changing the VHost, restart Apache and run `httpd.exe -t`; it should report `Syntax OK`.

The V8 public entrypoint explicitly loads `app/Security.php` before the template so the shared `e()` HTML-escaping helper is available on the public site.

## V18 — Content / Template separation

V18 treats website content and templates as separate layers.

- Changing a template never deletes or replaces existing sections.
- Hero is a normal editable section (`block_type=hero`) and can be edited, duplicated, hidden, reordered, or deleted.
- “Gunakan Template” changes presentation/layout only.
- “Import Demo” is additive and appends starter blocks without replacing existing content.
- “Pulihkan Layout Sebelumnya” restores the previous presentation/layout revision only; it does not restore or delete content.
- “Kembali ke Default” resets the skin to the Default presentation while preserving content.
- Revisions are stored in the `revisions` table for safe template/layout operations.

### Upgrade safety

For V18 upgrade packages, keep the existing `data/cms.sqlite`. The database is the source of truth for website content and should not be overwritten by a template/CMS code update.
