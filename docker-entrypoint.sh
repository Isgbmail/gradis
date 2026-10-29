#!/bin/sh
set -e
mkdir -p /data/db /data/uploads
# Conserve les protections du dossier uploads, puis le redirige vers le disque persistant
[ -f /data/uploads/.htaccess ] || cp /var/www/html/uploads/.htaccess /data/uploads/.htaccess 2>/dev/null || true
[ -f /data/uploads/index.html ] || touch /data/uploads/index.html
if [ ! -L /var/www/html/uploads ]; then rm -rf /var/www/html/uploads && ln -s /data/uploads /var/www/html/uploads; fi
chown -R www-data:www-data /data
exec apache2-foreground
