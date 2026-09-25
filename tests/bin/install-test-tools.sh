#!/usr/bin/env bash

set -euo pipefail

TEST_SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CLINOMICS_ROOT="$(cd "$TEST_SCRIPT_DIR/../.." && pwd)"
TESTING_DIR="$CLINOMICS_ROOT/storage/framework/testing"
LOCAL_NODE_ENV="$TESTING_DIR/node22"
PLAYWRIGHT_BROWSER_DIR="$TESTING_DIR/playwright-browsers"
LEGACY_NODE_DIR="$CLINOMICS_ROOT/app/bin/node/bin"
SELECTED_NODE_DIR=""
CONDA_EXECUTABLE=""

read_env_value() {
    local key="$1"
    local env_file="$CLINOMICS_ROOT/.env"

    if [[ ! -r "$env_file" ]]; then
        return 1
    fi

    awk -F= -v wanted="$key" '$1 == wanted {sub(/^[^=]*=/, ""); gsub(/^[[:space:]\"]+|[[:space:]\"\r]+$/, ""); print; exit}' "$env_file"
}

find_conda_executable() {
    local shell_conda_root="${CONDA_PATH:-}"
    local env_conda_root
    local candidate

    env_conda_root="$(read_env_value CONDA_PATH || true)"

    for candidate in "$shell_conda_root" "$env_conda_root"; do
        if [[ -n "$candidate" && -x "$candidate/bin/conda" ]]; then
            printf '%s\n' "$candidate/bin/conda"
            return
        fi
    done

    return 1
}

node_major_version() {
    local node_executable="$1"
    "$node_executable" --version 2>/dev/null | sed -E 's/^v([0-9]+).*/\1/'
}

select_node_runtime() {
    local node_executable="$LEGACY_NODE_DIR/node"
    local npm_executable="$LEGACY_NODE_DIR/npm"
    local node_major=""

    if [[ -x "$node_executable" && -x "$npm_executable" ]]; then
        node_major="$(node_major_version "$node_executable")"
        if [[ "$node_major" =~ ^[0-9]+$ ]] && (( node_major >= 22 )); then
            export PATH="$LEGACY_NODE_DIR:$PATH"
            SELECTED_NODE_DIR="$LEGACY_NODE_DIR"
            echo "Using Node from $LEGACY_NODE_DIR"
            return
        fi

        echo "Ignoring $LEGACY_NODE_DIR: Node ${node_major:-unknown} is too old for Playwright."
    fi

    if [[ ! -x "$LOCAL_NODE_ENV/bin/node" ]]; then
        CONDA_EXECUTABLE="$(find_conda_executable || true)"

        if [[ -z "$CONDA_EXECUTABLE" ]]; then
            echo "A modern Node.js runtime was not found." >&2
            echo "Set CONDA_PATH in .env to a readable Conda installation and rerun this script." >&2
            exit 1
        fi

        echo "Using Conda executable $CONDA_EXECUTABLE"
        echo "Installing Node.js 22 into $LOCAL_NODE_ENV"
        "$CONDA_EXECUTABLE" create -y --prefix "$LOCAL_NODE_ENV" -c conda-forge nodejs=22
    fi

    export PATH="$LOCAL_NODE_ENV/bin:$PATH"
    SELECTED_NODE_DIR="$LOCAL_NODE_ENV/bin"
    echo "Using user-owned Node from $LOCAL_NODE_ENV"
}

install_browser_runtime_libraries() {
    if [[ "$(uname -s)" != "Linux" || "$SELECTED_NODE_DIR" != "$LOCAL_NODE_ENV/bin" ]]; then
        return
    fi

    if [[ -z "$CONDA_EXECUTABLE" ]]; then
        CONDA_EXECUTABLE="$(find_conda_executable || true)"
    fi

    if [[ -z "$CONDA_EXECUTABLE" ]]; then
        echo "Conda is required to install Chromium runtime libraries without administrator access." >&2
        exit 1
    fi

    echo "Installing Chromium runtime libraries into $LOCAL_NODE_ENV"
    "$CONDA_EXECUTABLE" install -y --prefix "$LOCAL_NODE_ENV" -c conda-forge \
        alsa-lib at-spi2-atk at-spi2-core mesalib
    export LD_LIBRARY_PATH="$LOCAL_NODE_ENV/lib${LD_LIBRARY_PATH:+:$LD_LIBRARY_PATH}"
}

install_php_dependencies() {
    if command -v composer >/dev/null 2>&1; then
        composer install --prefer-dist --no-interaction --no-progress --ignore-platform-req=ext-oci8
        return
    fi

    if [[ -x "$CLINOMICS_ROOT/vendor/bin/phpunit" ]]; then
        echo "Composer is unavailable; existing vendor dependencies will be used."
        return
    fi

    echo "Composer is unavailable and vendor/bin/phpunit is missing." >&2
    echo "Install Composer in a user-owned location, then rerun this script." >&2
    exit 1
}

mkdir -p "$TESTING_DIR" "$PLAYWRIGHT_BROWSER_DIR"
cd "$CLINOMICS_ROOT"

command -v php >/dev/null 2>&1 || {
    echo "PHP is required but was not found in PATH." >&2
    exit 1
}

install_php_dependencies
select_node_runtime
install_browser_runtime_libraries

echo "Node: $(node --version)"
echo "npm:  $(npm --version)"

# The legacy application pins Vite 4 with laravel-vite-plugin 0.6, whose peer
# metadata only declares Vite 3. Preserve those versions for test installation.
npm install --no-audit --no-fund --legacy-peer-deps

PLAYWRIGHT_CLI="$CLINOMICS_ROOT/node_modules/.bin/playwright"
if [[ ! -x "$PLAYWRIGHT_CLI" ]]; then
    echo "Playwright was not installed from package.json." >&2
    exit 1
fi

echo "Installing Chromium into $PLAYWRIGHT_BROWSER_DIR"
PLAYWRIGHT_BROWSERS_PATH="$PLAYWRIGHT_BROWSER_DIR" \
    "$PLAYWRIGHT_CLI" install chromium

echo "Playwright: $($PLAYWRIGHT_CLI --version)"
PLAYWRIGHT_BROWSERS_PATH="$PLAYWRIGHT_BROWSER_DIR" \
    "$PLAYWRIGHT_CLI" test --project=portable --list

cat <<EOF

Testing tools are ready.

Portable PHP tests:
  php artisan test

Live Oracle/controller tests:
  RUN_INSTANCE_TESTS=1 php vendor/bin/phpunit -c phpunit.integration.xml

Portable browser tests:
  tests/bin/run-ui-tests.sh

Node, Chromium, and its runtime libraries are installed without administrator
privileges in storage/framework/testing.
EOF
