# Ara CMS V20.8.4 — First Install Guard

- Prevents fresh-install `no such table: sections` fatal errors.
- Detects installation state before loading Content.php.
- Redirects uninstalled sites to `install.php` with a clear notification.
- After successful install, redirects directly to `/admin/login.php`.
- Adds an installation guard to admin login and admin-required routes.
- Makes the `sections` typography migration safely no-op when the table does not yet exist.
- Keeps existing installed databases untouched.
