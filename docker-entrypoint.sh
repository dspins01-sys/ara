#!/bin/sh
set -eu

mkdir -p /var/www/html/data
mkdir -p /var/www/html/public/uploads

# Runtime directories must be writable by Apache/PHP.
chown www-data:www-data /var/www/html/data
chown www-data:www-data /var/www/html/public/uploads

chmod 775 /var/www/html/data
chmod 775 /var/www/html/public/uploads

exec apache2-foreground
