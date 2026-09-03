#!/bin/sh
set -eu

# Runtime directories used by Ara CMS.
mkdir -p /var/www/html/data
mkdir -p /var/www/html/public/uploads

# Only runtime directories need to be writable by Apache/PHP.
chown www-data:www-data /var/www/html/data
chown www-data:www-data /var/www/html/public/uploads

chmod 775 /var/www/html/data
chmod 775 /var/www/html/public/uploads

# Start Apache in foreground.
exec apache2-foreground
