#!/usr/bin/env bash

set -euo pipefail

TEST_SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CLINOMICS_ROOT="$(cd "$TEST_SCRIPT_DIR/../.." && pwd)"
TESTING_DIR="$CLINOMICS_ROOT/storage/framework/testing"
LOCAL_NODE_ENV="$TESTING_DIR/node22"

if [[ ! -x "$LOCAL_NODE_ENV/bin/node" ]]; then
    echo "The test Node.js runtime is missing. Run tests/bin/install-test-tools.sh first." >&2
    exit 1
fi

export PATH="$LOCAL_NODE_ENV/bin:$PATH"
export LD_LIBRARY_PATH="$LOCAL_NODE_ENV/lib${LD_LIBRARY_PATH:+:$LD_LIBRARY_PATH}"
export PLAYWRIGHT_BROWSERS_PATH="$TESTING_DIR/playwright-browsers"

cd "$CLINOMICS_ROOT"
exec npm run "${UI_TEST_SCRIPT:-test:ui}" -- "$@"
