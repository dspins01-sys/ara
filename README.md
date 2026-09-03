# Ara CMS

Lightweight PHP + SQLite CMS with a visual builder and framework-free frontend.

## Requirements
- PHP 8.1+ (8.3 recommended)
- PDO SQLite / SQLite3
- FileInfo
- OpenSSL
- Apache/LiteSpeed with `.htaccess`

## Install
1. Upload the project to the document root.
2. Ensure `data/` and `public/uploads/` are writable by PHP.
3. Open `/install.php` and create the first admin account.
4. After installation, the installer is locked/removed automatically.

See `README-CPANEL.md` for cPanel/XAMPP deployment notes.

## Repository hygiene
The repository contains source code, templates, and static demo assets only. Runtime SQLite data and uploaded media are intentionally excluded from Git.
