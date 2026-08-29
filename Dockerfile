# live-click (LiveGig) — PHP-app op Apache, met SQLite-opslag op een persistent volume.
#
# Mappenstructuur in de container (bootstrap.php verwacht APP_ROOT = twee niveaus
# boven de webroot):
#   /var/www/html/                  ← APP_ROOT
#     includes/                     ← PHP-includes (buiten de webroot)
#     data/                         ← PERSISTENT: SQLite-db, PDF's, tokencache
#     htdocs/live-click/            ← DocumentRoot (webroot)
#
# Configuratie gaat via omgevingsvariabelen (zie includes/config.php), o.a.:
#   LIVEGIG_SPOTIFY_CLIENT_ID / _SECRET, LIVEGIG_GETSONGBPM_API_KEY,
#   LIVEGIG_MOLLIE_API_KEY / _REDIRECT_URL / _WEBHOOK_URL / _SUBSCRIPTION_AMOUNT,
#   LIVEGIG_MOLLIE_BILLING_ENFORCED, LIVEGIG_STORAGE_QUOTA_MB
# Er wordt bewust géén config.local.php meegebouwd (zie .dockerignore).

FROM php:8.3-apache

# PHP-extensies: alleen pdo_sqlite moet erbij; curl en fileinfo zitten al in de image.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Apache: mod_rewrite aan (voor .htaccess) en DocumentRoot op de webroot zetten.
RUN set -eux; \
    a2enmod rewrite; \
    { \
      echo '<VirtualHost *:80>'; \
      echo '    DocumentRoot /var/www/html/htdocs/live-click'; \
      echo '    <Directory /var/www/html/htdocs/live-click>'; \
      echo '        AllowOverride All'; \
      echo '        Require all granted'; \
      echo '    </Directory>'; \
      echo '    ErrorLog ${APACHE_LOG_DIR}/error.log'; \
      echo '    CustomLog ${APACHE_LOG_DIR}/access.log combined'; \
      echo '</VirtualHost>'; \
    } > /etc/apache2/sites-available/000-default.conf; \
    echo 'ServerName localhost' >> /etc/apache2/apache2.conf

# PHP-instellingen: PDF-uploads tot 10 MB toestaan + nette productiedefaults.
RUN { \
      echo 'upload_max_filesize = 12M'; \
      echo 'post_max_size = 14M'; \
      echo 'memory_limit = 256M'; \
      echo 'date.timezone = UTC'; \
      echo 'expose_php = Off'; \
    } > /usr/local/etc/php/conf.d/livegig.ini

# Applicatiecode.
COPY . /var/www/html/

# Persistente datamap (SQLite-db, PDF's, tokencache), schrijfbaar voor Apache.
# Op een cluster mount je hier een PersistentVolume; gebruik fsGroup: 33 (www-data)
# zodat het gemounte volume schrijfbaar is.
RUN mkdir -p /var/www/html/data/pdfs \
    && chown -R www-data:www-data /var/www/html/data

VOLUME ["/var/www/html/data"]
EXPOSE 80
# De base-image start Apache in de voorgrond (CMD apache2-foreground).
