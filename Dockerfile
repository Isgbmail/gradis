# Gradis — image PHP 8.3 + Apache (SQLite). Prévue pour Render / Railway / Fly.io.
FROM php:8.3-apache

# .htaccess actifs, suivi des liens symboliques, HTTPS détecté derrière le proxy de l'hébergeur
RUN a2enmod rewrite headers \
 && printf '<Directory /var/www/html>\n  AllowOverride All\n  Options -Indexes +FollowSymLinks\n</Directory>\nSetEnvIf X-Forwarded-Proto https HTTPS=on\n' > /etc/apache2/conf-available/gradis.conf \
 && a2enconf gradis

COPY . /var/www/html/
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh && chown -R www-data:www-data /var/www/html

# Base et photos envoyées vivent sur un disque persistant monté sur /data
ENV DB_PATH=/data/db
EXPOSE 80
ENTRYPOINT ["docker-entrypoint.sh"]
