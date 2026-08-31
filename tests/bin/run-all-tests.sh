#!/usr/bin/env bash

set -uo pipefail

TEST_SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CLINOMICS_ROOT="$(cd "$TEST_SCRIPT_DIR/../.." && pwd)"

PASSED_SUITES=()
FAILED_SUITES=()
SKIPPED_SUITES=()

run_suite() {
    local label="$1"
    shift

    printf '\n== %s ==\n' "$label"
    "$@"
    local status=$?

    if (( status == 0 )); then
        PASSED_SUITES+=("$label")
    else
        FAILED_SUITES+=("$label (exit $status)")
    fi
}

skip_suite() {
    local label="$1"
    local reason="$2"
    printf '\n== %s ==\nSKIPPED: %s\n' "$label" "$reason"
    SKIPPED_SUITES+=("$label")
}

print_list() {
    local heading="$1"
    shift
    printf '%s: %d\n' "$heading" "$#"
    for item in "$@"; do
        printf '  - %s\n' "$item"
    done
}

cd "$CLINOMICS_ROOT" || exit 1

if [[ ! -x vendor/bin/phpunit ]]; then
    echo "vendor/bin/phpunit is missing. Run tests/bin/install-test-tools.sh first." >&2
    exit 1
fi

run_suite "PHP syntax/static checks" php tests/bin/lint-php.php
run_suite "Portable PHP architecture, unit, and feature tests" php vendor/bin/phpunit

if [[ "${RUN_LIVE_TESTS:-1}" == "1" ]]; then
    run_suite \
        "Live database and authenticated controller tests (pseudo user)" \
        env RUN_INSTANCE_TESTS=1 php vendor/bin/phpunit --configuration phpunit.integration.xml
else
    skip_suite \
        "Live database and authenticated controller tests (pseudo user)" \
        "RUN_LIVE_TESTS is not 1."
fi

if [[ "${RUN_BROWSER_TESTS:-1}" == "1" ]]; then
    run_suite "Portable guest-access and login-page Playwright tests" tests/bin/run-ui-tests.sh
else
    skip_suite "Portable guest-access and login-page Playwright tests" "RUN_BROWSER_TESTS is not 1."
fi

if [[ "${RUN_ACTUAL_VIEW_TESTS:-1}" == "1" ]]; then
    run_suite \
        "Actual authenticated view tests (live data, pseudo user)" \
        env -u E2E_BASE_URL -u E2E_STORAGE_STATE RUN_INSTANCE_UI=1 RUN_LOCAL_INSTANCE_UI=1 UI_TEST_SCRIPT=test:ui:instance tests/bin/run-ui-tests.sh
else
    skip_suite \
        "Actual authenticated view tests (live data, pseudo user)" \
        "RUN_ACTUAL_VIEW_TESTS is not 1."
fi

if [[ "${RUN_ACCESSIBILITY_TESTS:-1}" == "1" ]]; then
    run_suite \
        "Authenticated accessibility audit (live data, pseudo user)" \
        env -u E2E_BASE_URL -u E2E_STORAGE_STATE RUN_INSTANCE_UI=1 RUN_LOCAL_INSTANCE_UI=1 RUN_ACCESSIBILITY_UI=1 UI_TEST_SCRIPT=test:ui:accessibility tests/bin/run-ui-tests.sh
else
    skip_suite \
        "Authenticated accessibility audit (live data, pseudo user)" \
        "RUN_ACCESSIBILITY_TESTS is not 1."
fi

if [[ "${RUN_DEPLOYED_UI_TESTS:-0}" == "1" ]]; then
    run_suite \
        "Authenticated deployed-instance Playwright tests" \
        env RUN_INSTANCE_UI=1 RUN_LOCAL_INSTANCE_UI=0 UI_TEST_SCRIPT=test:ui:instance tests/bin/run-ui-tests.sh
else
    skip_suite \
        "Authenticated deployed-instance Playwright tests" \
        "Set RUN_DEPLOYED_UI_TESTS=1 only with a real E2E_STORAGE_STATE."
fi

printf '\n========== Final test summary ==========\n'
print_list "Passed suites" "${PASSED_SUITES[@]}"
print_list "Failed suites" "${FAILED_SUITES[@]}"
print_list "Skipped suites" "${SKIPPED_SUITES[@]}"

if (( ${#FAILED_SUITES[@]} > 0 )); then
    exit 1
fi

printf '\nAll enabled test suites passed.\n'
