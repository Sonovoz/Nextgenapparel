#!/bin/sh
# Arranca o actualiza un entorno con un solo comando:
#   ./docker/desplegar.sh pruebas
#   ./docker/desplegar.sh produccion
# Añade «--sin-proxy» para probarlo sin Caddy (solo en 127.0.0.1:PUERTO_WEB).
set -e

entorno="$1"
case "$entorno" in
    pruebas|produccion) ;;
    *) echo "Uso: $0 pruebas|produccion [--sin-proxy]" >&2; exit 1 ;;
esac

cd "$(dirname "$0")/.."

export ENV_FILE="docker/env/$entorno.env"
export COMPOSE_PROJECT_NAME="nga-$entorno"
export VERSION="$(git rev-parse --short HEAD 2>/dev/null || echo local)"

if [ ! -f "$ENV_FILE" ]; then
    echo "Falta $ENV_FILE: cópialo de $ENV_FILE.example y rellénalo." >&2
    exit 1
fi

archivos="-f compose.yaml"
if [ "$2" != "--sin-proxy" ]; then
    docker network inspect proxy > /dev/null 2>&1 || docker network create proxy > /dev/null
    archivos="$archivos -f compose.proxy.yaml"
fi

perfil=""
[ "$entorno" = "pruebas" ] && perfil="--profile pruebas"

# shellcheck disable=SC2086
docker compose $archivos --env-file "$ENV_FILE" $perfil up -d --build --wait

echo "Entorno «$entorno» en marcha (versión $VERSION)."
