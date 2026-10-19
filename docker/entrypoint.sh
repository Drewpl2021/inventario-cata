#!/bin/sh
#
# Entrada de SIBIP en Docker. La usan los tres servicios con la misma imagen:
#   web (comando /init):   espera la base, clave, migraciones, seeders y cachés.
#   cola / programador:    espera la base y la clave, y prepara las cachés.
# Al final pasa el control a la entrada de la imagen base (serversideup), que
# arranca Nginx + PHP-FPM o ejecuta el comando indicado.

set -e
cd /var/www/html

ARCHIVO_CLAVE=storage/app/sistema/app_key
ES_WEB=false
[ "${1:-}" = "/init" ] && ES_WEB=true

log() { echo "[sibip] $*"; }

esperar_base() {
    log "Esperando la base de datos en ${DB_HOST:-db}:${DB_PORT:-3306}..."
    intentos=0
    until php -r '
        try {
            new PDO("mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: 3306).";dbname=".getenv("DB_DATABASE"),
                getenv("DB_USERNAME"), getenv("DB_PASSWORD"), [PDO::ATTR_TIMEOUT => 3]);
            exit(0);
        } catch (Throwable $e) { exit(1); }' 2>/dev/null; do
        intentos=$((intentos + 1))
        if [ "$intentos" -ge 90 ]; then
            log "ERROR: la base de datos no respondió en 3 minutos. Revise el servicio db: docker compose logs db"
            exit 1
        fi
        sleep 2
    done
    log "Base de datos lista."
}

# APP_KEY: la del .env si está definida; si no, la guardada en el volumen; si no hay,
# la genera el servicio web una sola vez (la cola y el programador la esperan).
preparar_clave() {
    if [ -n "${APP_KEY:-}" ]; then
        return
    fi
    if [ ! -s "$ARCHIVO_CLAVE" ]; then
        if [ "$ES_WEB" = "true" ]; then
            php artisan key:generate --show --no-ansi > "$ARCHIVO_CLAVE.tmp"
            mv "$ARCHIVO_CLAVE.tmp" "$ARCHIVO_CLAVE"
            chmod 600 "$ARCHIVO_CLAVE"
            log "APP_KEY estaba vacía: se generó una y se guardó en el volumen (storage/app/sistema)."
            log "Recomendado: cópiela al .env con  docker compose exec app cat $ARCHIVO_CLAVE"
        else
            log "Esperando la APP_KEY que genera el servicio web..."
            espera=0
            until [ -s "$ARCHIVO_CLAVE" ]; do
                espera=$((espera + 1))
                [ "$espera" -ge 90 ] && { log "ERROR: no apareció la APP_KEY."; exit 1; }
                sleep 2
            done
        fi
    fi
    APP_KEY=$(cat "$ARCHIVO_CLAVE")
    export APP_KEY
}

esperar_base
preparar_clave

if [ "$ES_WEB" = "true" ]; then
    log "Migraciones..."
    php artisan migrate --force --no-interaction

    log "Datos base (roles, permisos y Administrador inicial)..."
    php artisan db:seed --class='Database\Seeders\RolesYPermisosSeeder' --force --no-interaction
    php artisan db:seed --class='Database\Seeders\AdministradorInicialSeeder' --force --no-interaction

    if [ "${SIBIP_SEED_DEMO:-false}" = "true" ]; then
        log "SIBIP_SEED_DEMO=true: cargando datos de demostración (solo crea lo que falta)..."
        php artisan db:seed --class='Database\Seeders\DemoSeeder' --force --no-interaction
    fi

    if [ -d public/uploads ]; then
        php artisan sibip:migrar-archivos --no-interaction
    fi
fi

log "Cachés de configuración, rutas, vistas y eventos..."
php artisan optimize --no-interaction

exec docker-php-serversideup-entrypoint "$@"
