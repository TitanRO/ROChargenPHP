# doctl registry login --expiry-seconds 3600 && docker build -t registry.digitalocean.com/titanro-docr/rochargen . && docker image push registry.digitalocean.com/titanro-docr/rochargen
# Normally built by Tekton Pipelines-as-Code (.tekton/push.yaml) on pushes to master; titanro-ci[bot] pins the digest in
# TitanRO/sysops argo/rochargen, and Argo CD deploys it. The line above is the manual fallback.

FROM php:8-apache

RUN apt-get update -y && apt-get install -y libpng-dev
RUN docker-php-ext-install pdo pdo_mysql mysqli gd
RUN a2enmod rewrite

COPY ./cache /var/www/html/cache
COPY ./client /var/www/html/client
COPY ./controllers /var/www/html/controllers
COPY ./core /var/www/html/core
COPY ./db /var/www/html/db
COPY ./loaders /var/www/html/loaders
COPY ./render /var/www/html/render
COPY ./.htaccess ./index.php ./README.md /var/www/html/

RUN chown -R www-data:www-data /var/www
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
