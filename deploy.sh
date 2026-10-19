#!/usr/bin/env bash
#
# Actualiza SIBIP en el VPS con la última versión de la rama.
#
#   cd /var/www/sibip && ./deploy.sh
#
# Se ejecuta con el usuario dueño del código (sibip), no con root.
# Variables opcionales: RAMA (por defecto main) y PHP (por defecto php).
# Si un paso falla, el script se detiene y el sitio queda en mantenimiento
# para no mostrar una versión a medias. Ver DEPLOY.md, "Actualizar".

set -Eeuo pipefail

RAMA="${RAMA:-main}"
PHP="${PHP:-php}"
cd "$(dirname "$(readlink -f "$0")")"

paso() { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }

fallo() {
    printf '\n\033[1;31m✘ Falló el paso: %s (línea %s).\033[0m\n' "$BASH_COMMAND" "$1" >&2
    if [ "${EN_MANTENIMIENTO:-0}" = "1" ]; then
        printf 'El sitio sigue en MANTENIMIENTO. Corrija el problema y vuelva a ejecutar ./deploy.sh.\n' >&2
        printf 'Para reabrirlo sin actualizar: %s artisan up\n' "$PHP" >&2
    fi
}
trap 'fallo $LINENO' ERR

# --- Comprobaciones antes de tocar nada ---------------------------------------
if [ "$(id -u)" -eq 0 ]; then
    echo "No ejecute este script como root: use el usuario dueño del código (sibip)." >&2
    exit 1
fi
[ -f .env ] || { echo "Falta el archivo .env (vea DEPLOY.md)." >&2; exit 1; }
if ! git diff --quiet || ! git diff --cached --quiet; then
    echo "Hay cambios sin confirmar en el servidor (git status). No se actualiza para no perderlos." >&2
    exit 1
fi

# --- Actualización -------------------------------------------------------------
paso "Modo mantenimiento"
"$PHP" artisan down --retry=60 --refresh=30
EN_MANTENIMIENTO=1

paso "Descargando la rama $RAMA"
git fetch --prune origin "$RAMA"
git merge --ff-only "origin/$RAMA"
git log -1 --format='Versión: %h · %s (%ci)'

paso "Dependencias de PHP"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

paso "Estilos y scripts"
npm ci --no-audit --no-fund
npm run build

paso "Base de datos"
"$PHP" artisan optimize:clear
"$PHP" artisan migrate --force

paso "Cachés de configuración, rutas, vistas y eventos"
"$PHP" artisan optimize

paso "Reinicio del worker de la cola"
# El worker termina el trabajo actual y systemd lo vuelve a levantar con el código nuevo.
"$PHP" artisan queue:restart

paso "Saliendo del modo mantenimiento"
"$PHP" artisan up
EN_MANTENIMIENTO=0

paso "Verificación"
"$PHP" artisan sibip:verificar-produccion || printf '\n\033[1;33mEl sitio ya está actualizado, pero revise los puntos en rojo.\033[0m\n'

printf '\n\033[1;32m✔ Actualización terminada.\033[0m\n'
