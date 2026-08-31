# Automated testing

The automated checks are split into a portable gate, browser journeys, and an
explicitly enabled instance profile. The portable gate forces an in-memory
SQLite database, array-backed cache/session/mail, and a synchronous queue. Its
bootstrap aborts if those safeguards are not active.

## Local commands

To run every test layer available with the pseudo test user, use:

```bash
tests/bin/run-all-tests.sh
```

This runs PHP syntax checks, the complete portable PHPUnit suite, live
database/controller integration tests authenticated directly as `TEST_USER_ID`,
portable guest-access Playwright tests, and actual protected views against live
instance data. The actual-view server listens only on loopback and uses a
per-run test token to authenticate directly as the pseudo user; it never enables
the bypass outside `APP_ENV=testing`.
The script continues after a failed category and returns a nonzero status after
printing a final category summary.

Set `RUN_LIVE_TESTS=0`, `RUN_BROWSER_TESTS=0`, or
`RUN_ACTUAL_VIEW_TESTS=0`, or `RUN_ACCESSIBILITY_TESTS=0` to omit those layers. Set
`RUN_DEPLOYED_UI_TESTS=1` only when `E2E_BASE_URL` and a real
`E2E_STORAGE_STATE` are available.

Install the dependencies from the committed lock file, then run:

```bash
composer test
```

The complete portable gate runs syntax validation followed by architecture,
unit, and feature suites. Individual checks are available while developing:

```bash
composer test:static
composer test:architecture
composer test:unit
composer test:feature
```

The equivalent framework command remains available:

```bash
php artisan test
```

On a server without a current system Node.js installation, install all testing
tools—including a user-owned Node 22 runtime and Chromium—with:

```bash
tests/bin/install-test-tools.sh
```

The installer checks `app/bin/node/bin/npm` first. If its accompanying Node.js
is older than version 22, it creates an isolated environment below
`storage/framework/testing/` using `CONDA_PATH` from `.env`. On Linux it also
installs Chromium's runtime libraries in that environment. It never replaces
the application's legacy Node.js distribution and does not require root access.
It uses npm's legacy peer-resolution mode because this application intentionally
pairs Vite 4 with an older Laravel Vite plugin peer declaration.

The architecture suite validates the committed contract for every application
route, compiles every Blade view to parseable PHP, and loads every active model.
The feature suite also verifies that every GET route protected by `logged`
middleware rejects guests before its controller is reached.

## Browser tests

Install Node dependencies, Chromium, and its user-owned runtime libraries once,
then run the portable journeys through the environment wrapper:

```bash
tests/bin/install-test-tools.sh
tests/bin/run-ui-tests.sh
```

Playwright starts an isolated local Laravel server automatically. The tests
check the login page for JavaScript errors, HTTP 5xx responses, and serious
accessibility violations, then exercise representative protected routes as a
guest. Traces, screenshots, videos, and the HTML report are written below
`storage/framework/testing/` when a test fails.

The portable browser matrix is generated from `tests/Fixtures/routes.json` and
checks every GET route whose URI begins with `view` and uses the `logged`
middleware. This prevents newly added view routes from silently missing guest
authorization coverage.

Authenticated instance UI tests run against an already deployed site. Export a
Playwright storage-state file created for a non-privileged test user:

```bash
RUN_INSTANCE_UI=1 \
E2E_BASE_URL=https://test.example/clinomics/public \
E2E_STORAGE_STATE=/secure/path/auth-state.json \
UI_TEST_SCRIPT=test:ui:instance tests/bin/run-ui-tests.sh
```

`/secure/path/auth-state.json` is an example placeholder, not a file supplied
by the repository. Replace it with an existing Playwright storage-state JSON
file containing the authenticated session for the configured test user. The
suite validates these prerequisites once and stops with one setup error when
the URL or state file is missing or invalid.

The authenticated suite inventories every `view*` GET route. Each safe,
data-backed route must render below HTTP 400, remain on the actual application
view rather than the login page, produce no uncaught JavaScript or failed
resource/data requests, expose a non-empty visible body, initialize visible
tables/charts, and support its local tabs. The dedicated case suite additionally validates case and
pipeline identity, summary rows, capability-backed EasyUI tabs, links, and
duplicate DOM IDs.

Accessibility is reported as a separate suite so a shared markup violation is
not mislabeled as a failure to render or authenticate a view. It audits the
representative project, patient, case, and cancer-type pages for serious or
critical WCAG 2 A/AA violations (excluding color contrast). Run it alone with:

```bash
UI_TEST_SCRIPT=test:ui:accessibility tests/bin/run-ui-tests.sh
```

or let `tests/bin/run-all-tests.sh` run it automatically.

Instance-specific inputs live in the JSON file selected by
`TEST_INSTANCE_FIXTURES`. A route that cannot be exercised safely must appear
in `view_route_exclusions` with a meaningful reason. The coverage contract
fails when a route is neither tested nor explicitly excluded.

## Instance integration profile

The normal test command never connects to Oracle or requires mounted result
files. To validate a particular installation, copy and edit an instance JSON
profile under `tests/Fixtures/instances/`, then configure its path in `.env`:

```dotenv
TEST_INSTANCE_FIXTURES=tests/Fixtures/instances/clinomics_dev.json
TEST_USER_ID=4
```

Opt in explicitly when running the live checks:

```bash
RUN_INSTANCE_TESTS=1 composer test:integration
```

The authenticated controller checks log in through the application's Sentry
authentication interface using `TEST_USER_ID`. Use an active, non-privileged
test account with access to every cohort in the selected fixture. Only the
explicitly allowlisted read-oriented endpoints are exercised.

The `clinomics_dev` profile uses project 22112 for the primary multimodal case,
project 25062 for COMPASS panel/exome coverage, and `Neuroblastoma` for
cancer-type coverage. It verifies project, patient, case, and cancer-type
membership; sample/result modalities; molecular results; and mounted result
directories. The authenticated browser profile also covers the cancer-type
catalog, details, expression, mutation, fusion, QC, TIL, and ChIP-seq pages.
These checks are read-only. Project 27303 is intentionally not a fixture
dependency.

## HTML test catalog

Generate an HTML document containing every discovered portable PHP test:

```bash
php artisan test --testdox-html docs/all-tests.html
```

This runs the suite and replaces `docs/all-tests.html` with the current TestDox
results. The static syntax scan is a separate check and is run with
`composer test:static`.

## When tests run

GitHub Actions runs the portable PHP gate with PHP 8.3 and PHP 8.5 and runs the
portable Chromium journeys with PHP 8.3. This happens for pull requests, manual
workflow dispatches, and pushes to `main`, `chatbot`, or
`laravel-13-upgrade`.

No automatic CI test contacts live LDAP, real AI providers, pipeline scripts,
Oracle, or production filesystem mounts. Oracle/filesystem and authenticated UI
coverage remain explicit instance checks because they require site-specific
fixtures and credentials.
