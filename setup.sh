#!/bin/sh
set -eu

cd "$(dirname "$0")"

if ! command -v php >/dev/null 2>&1; then
    echo "PHP CLI is not on PATH. Load the production PHP environment before running setup.sh." >&2
    exit 1
fi

if [ -n "${COMPOSER_BIN:-}" ]; then
    composer_path=$COMPOSER_BIN
elif command -v composer >/dev/null 2>&1; then
    composer_path=$(command -v composer)
elif [ -f ./composer.phar ]; then
    composer_path=./composer.phar
else
    cat >&2 <<'MESSAGE'
Composer was not found. Install Composer using https://getcomposer.org/download/
(or load the environment that provides it), then rerun setup.sh.
You can place composer.phar beside setup.sh or specify an existing installation:
  COMPOSER_BIN=/absolute/path/to/composer sh setup.sh
  COMPOSER_BIN=/absolute/path/to/composer.phar sh setup.sh
MESSAGE
    exit 1
fi

case "$composer_path" in
    *.phar)
        if [ ! -r "$composer_path" ]; then
            echo "Composer PHAR is not readable: $composer_path" >&2
            exit 1
        fi
        ;;
    *)
        if ! command -v "$composer_path" >/dev/null 2>&1; then
            echo "Composer executable was not found: $composer_path" >&2
            exit 1
        fi
        ;;
esac

run_composer() {
    case "$composer_path" in
        *.phar) php "$composer_path" "$@" ;;
        *) "$composer_path" "$@" ;;
    esac
}

link_if_missing() {
    link_target=$1
    link_name=${2:-$(basename "$link_target")}
    if [ ! -e "$link_name" ] && [ ! -L "$link_name" ]; then
        ln -s "$link_target" "$link_name"
    fi
}

mkdir -p bootstrap/cache
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php
run_composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
mkdir -p storage/logs
mkdir -p storage/framework/views
mkdir -p storage/framework/cache
chmod g+w storage/logs
chmod g+w storage/framework/views
chmod g+w storage/framework/cache
cd app
link_if_missing ../site_data/app/bin
link_if_missing ../site_data/app/metadata
link_if_missing ../site_data/app/ref
link_if_missing ../site_data/app/scripts
cd ../storage
link_if_missing ../site_data/storage/bams
link_if_missing ../site_data/storage/data_integrity_report
link_if_missing ../site_data/storage/ProcessedResults
link_if_missing ../site_data/storage/project_data
link_if_missing ../site_data/storage/sync
cd ../public
link_if_missing ../site_data/app/ref
