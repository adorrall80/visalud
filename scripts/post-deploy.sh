#!/bin/bash

set -e

PHP84=${PHP84:-/opt/cpanel/ea-php84/root/usr/bin/php}
COMPOSER_BIN=${COMPOSER_BIN:-/usr/local/bin/composer}
TEST_PATH=${TEST_PATH:-/home/jatoyris/visalud.esremate.cl/pruebas}
PROD_PATH=${PROD_PATH:-/home/jatoyris/visalud.esremate.cl}

RUN_COMPOSER=false
RUN_FRESH_DEMO=false
RUN_SEED_INITIAL=false
APP_PATH=""
APP_ENVIRONMENT=""

print_help() {
    echo "Uso: bash scripts/post-deploy.sh --test|--prod [opciones]"
    echo ""
    echo "Ambiente:"
    echo "  --test                 Usar carpeta de pruebas: $TEST_PATH"
    echo "  --prod                 Usar carpeta de produccion: $PROD_PATH"
    echo "  --path=/ruta/app       Usar una ruta manual en vez de --test o --prod"
    echo ""
    echo "Opciones:"
    echo "  --composer             Reinstalar vendor con composer install --no-dev"
    echo "  --seed-initial         Ejecutar php console db:seed despues de migrar"
    echo "  --fresh-demo           Reconstruir la base con migrate:fresh --seed"
    echo "  --help                 Mostrar esta ayuda"
    echo ""
    echo "Ejemplos:"
    echo "  bash scripts/post-deploy.sh --test"
    echo "  bash scripts/post-deploy.sh --test --composer"
    echo "  bash scripts/post-deploy.sh --prod --seed-initial"
}

for arg in "$@"; do
    case "$arg" in
        --help|-h)
            print_help
            exit 0
            ;;
        --test)
            APP_PATH="$TEST_PATH"
            APP_ENVIRONMENT="test"
            ;;
        --prod)
            APP_PATH="$PROD_PATH"
            APP_ENVIRONMENT="prod"
            ;;
        --path=*)
            APP_PATH="${arg#--path=}"
            ;;
        --composer)
            RUN_COMPOSER=true
            ;;
        --fresh-demo)
            RUN_FRESH_DEMO=true
            ;;
        --seed-initial)
            RUN_SEED_INITIAL=true
            ;;
        *)
            echo "ERROR: argumento no reconocido: $arg"
            exit 1
            ;;
    esac
done

if [ -z "$APP_PATH" ]; then
    echo "ERROR: debes indicar ambiente: --test o --prod"
    echo ""
    print_help
    exit 1
fi

cd "$APP_PATH"

if [ ! -f console ]; then
    echo "ERROR: no se encontro console en $APP_PATH"
    exit 1
fi

echo "== PHP =="
$PHP84 -v

echo "== Carpeta =="
pwd

if [ "$RUN_COMPOSER" = true ]; then
    echo "== Reinstalar vendor =="
    rm -rf vendor
    $PHP84 "$COMPOSER_BIN" install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress
fi

if [ ! -f vendor/autoload.php ]; then
    echo "ERROR: falta vendor/autoload.php"
    echo "Ejecuta de nuevo con --composer o instala dependencias en el hosting."
    exit 1
fi

echo "== Crear carpetas de escritura =="
mkdir -p storage/cache storage/logs storage/documentos
chmod -R 775 storage/cache storage/logs storage/documentos

echo "== Base de datos =="
if [ "$RUN_FRESH_DEMO" = true ]; then
    echo "== Modo fresh demo: reconstruir base y cargar demo =="
    $PHP84 console migrate:fresh --seed
else
    echo "== Modo normal: aplicar migraciones pendientes =="
    $PHP84 console migrate

    if [ "$RUN_SEED_INITIAL" = true ]; then
        echo "== Cargar datos iniciales =="
        $PHP84 console db:seed
    fi
fi

echo "== Limpiar OPcache =="
$PHP84 -r "if (function_exists('opcache_reset')) { opcache_reset(); echo 'OPcache cleared'.PHP_EOL; }"

echo "== Verificacion =="
if [ "$APP_ENVIRONMENT" = "prod" ]; then
    $PHP84 console app:check --production
else
    $PHP84 console app:check
fi
$PHP84 console migrate:status

echo "Post deploy terminado OK"
