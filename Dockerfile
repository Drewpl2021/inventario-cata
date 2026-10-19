# syntax=docker/dockerfile:1
#
# SIBIP · imagen de producción (web, cola y programador usan esta misma imagen).
#
# Base: serversideup/php:8.4-fpm-nginx, en lugar de php:8.4-fpm + Nginx armados a mano:
# - Trae Nginx y PHP-FPM ya supervisados (s6-overlay) en un solo contenedor, con
#   configuración de producción para Laravel (raíz en public/, sin index de carpetas).
# - Corre como www-data (sin root) desde el inicio, escuchando en el puerto 8080.
# - Los límites de PHP y Nginx se ajustan con variables de entorno, sin copiar php.ini.
# - Incluye install-php-extensions para agregar gd, exif e intl sin compilar a mano.
# Con php:8.4-fpm habría que instalar y supervisar Nginx, crear el usuario y escribir
# toda esa configuración nosotros.

ARG PHP_IMAGE=serversideup/php:8.4-fpm-nginx

############################################
# Base: PHP 8.4 + extensiones + cliente MySQL
############################################
FROM ${PHP_IMAGE} AS base

USER root

# gd (WebP, FreeType, JPEG), exif e intl. Las demás (fileinfo, mbstring, pdo_mysql,
# zip, xml, opcache) ya vienen en la imagen. mariadb-client aporta mysqldump.
RUN install-php-extensions gd exif intl \
    && apt-get update \
    && apt-get install -y --no-install-recommends mariadb-client \
    && rm -rf /var/lib/apt/lists/*

# El cliente de MariaDB 11 exige certificado verificado por defecto; MySQL dentro de
# la red interna de Docker usa uno autofirmado.
COPY docker/mysql-cliente.cnf /etc/mysql/conf.d/sibip.cnf

ENV PHP_DATE_TIMEZONE=America/Lima \
    PHP_UPLOAD_MAX_FILE_SIZE=12M \
    PHP_POST_MAX_SIZE=12M \
    PHP_MEMORY_LIMIT=256M \
    PHP_MAX_EXECUTION_TIME=120 \
    PHP_OPCACHE_ENABLE=1 \
    NGINX_CLIENT_MAX_BODY_SIZE=12M \
    AUTORUN_ENABLED=false \
    SHOW_WELCOME_MESSAGE=false \
    TZ=America/Lima

USER www-data
WORKDIR /var/www/html

############################################
# Etapa Composer: dependencias de PHP sin las de desarrollo
############################################
FROM base AS composer

COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist --no-progress

COPY --chown=www-data:www-data . .
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && rm -rf /composer/cache

############################################
# Etapa Node: estilos y scripts con Vite
############################################
FROM node:22-alpine AS node

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
# Tailwind también lee las vistas de paginación de Laravel.
COPY --from=composer /var/www/html/vendor/laravel/framework/src/Illuminate/Pagination/resources/views \
     ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

############################################
# Imagen final
############################################
FROM base AS final

COPY --chown=www-data:www-data --from=composer /var/www/html /var/www/html
COPY --chown=www-data:www-data --from=node /app/public/build /var/www/html/public/build
COPY --chown=root:root --chmod=755 docker/entrypoint.sh /usr/local/bin/sibip-entrypoint

# Carpetas que se montan como volúmenes: deben existir con el dueño correcto para que
# Docker copie esos permisos al crear el volumen.
RUN mkdir -p storage/app/private storage/app/respaldos storage/app/sistema \
        storage/framework/cache storage/framework/sessions storage/framework/views \
        storage/logs bootstrap/cache \
    && rm -f public/hot

ENTRYPOINT ["sibip-entrypoint"]
CMD ["/init"]

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=120s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8080/up > /dev/null || exit 1
