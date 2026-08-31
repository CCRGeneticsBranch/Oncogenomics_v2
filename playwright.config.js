require('dotenv').config();
const { defineConfig, devices } = require('@playwright/test');
const crypto = require('crypto');

const usingLocalInstance = process.env.RUN_LOCAL_INSTANCE_UI === '1';
const localPort = usingLocalInstance ? 8788 : 8787;
const baseURL = process.env.E2E_BASE_URL || `http://127.0.0.1:${localPort}`;
const usingExternalServer = Boolean(process.env.E2E_BASE_URL);
const testUserId = process.env.TEST_USER_ID || '4';
if (usingLocalInstance && !process.env.E2E_TEST_AUTH_TOKEN) {
  process.env.E2E_TEST_AUTH_TOKEN = crypto.randomBytes(32).toString('hex');
}
const browserAuthToken = process.env.E2E_TEST_AUTH_TOKEN;
const localStorageState = 'storage/framework/testing/local-instance-auth.json';
const portableServer = 'php artisan view:clear --quiet && APP_ENV=testing APP_DEBUG=false APP_KEY=base64:OzcF3n3F3sVgGYJxNq6bMkx04S2lFsqEqmU+VnW6PJM= APP_URL=http://127.0.0.1:8787 DB_CONNECTION=sqlite DB_DATABASE=:memory: CACHE_DRIVER=array SESSION_DRIVER=array MAIL_MAILER=array QUEUE_CONNECTION=sync php artisan serve --host=127.0.0.1 --port=8787';
const instanceServer = `php artisan view:clear --quiet && APP_ENV=testing APP_DEBUG=false APP_URL=http://127.0.0.1:${localPort} CACHE_DRIVER=array SESSION_DRIVER=file SESSION_SECURE_COOKIE=false MAIL_MAILER=array QUEUE_CONNECTION=sync E2E_TEST_USER_ID=${testUserId} E2E_TEST_AUTH_TOKEN=${browserAuthToken} php artisan serve --host=127.0.0.1 --port=${localPort}`;

module.exports = defineConfig({
  globalSetup: require.resolve('./tests/Browser/instance-global-setup'),
  testDir: './tests/Browser',
  outputDir: 'storage/framework/testing/playwright-results',
  timeout: usingLocalInstance ? 90_000 : 30_000,
  expect: { timeout: usingLocalInstance ? 20_000 : 8_000 },
  fullyParallel: false,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI
    ? [['line'], ['html', { outputFolder: 'storage/framework/testing/playwright-report', open: 'never' }]]
    : [['list'], ['html', { outputFolder: 'storage/framework/testing/playwright-report', open: 'never' }]],
  use: {
    baseURL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    ignoreHTTPSErrors: false,
  },
  webServer: usingExternalServer ? undefined : {
    command: usingLocalInstance ? instanceServer : portableServer,
    url: baseURL,
    reuseExistingServer: !process.env.CI,
    timeout: 120_000,
    stdout: 'pipe',
    stderr: 'pipe',
  },
  projects: [
    {
      name: 'portable',
      testIgnore: /instance\.spec\.js/,
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'instance',
      testMatch: /instance\.spec\.js/,
      use: {
        ...devices['Desktop Chrome'],
        storageState: usingLocalInstance ? localStorageState : (process.env.E2E_STORAGE_STATE || undefined),
      },
    },
  ],
});
