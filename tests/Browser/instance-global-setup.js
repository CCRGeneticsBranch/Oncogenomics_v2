const fs = require('fs');
const path = require('path');
const { request } = require('@playwright/test');

module.exports = async function instanceGlobalSetup() {
  if (process.env.RUN_INSTANCE_UI !== '1') return;
  if (process.env.RUN_LOCAL_INSTANCE_UI === '1') {
    const stateFile = path.resolve(process.cwd(), 'storage/framework/testing/local-instance-auth.json');
    const context = await request.newContext({
      baseURL: 'http://127.0.0.1:8788',
      extraHTTPHeaders: { 'X-Clinomics-Test-Auth': process.env.E2E_TEST_AUTH_TOKEN },
    });
    const response = await context.get('/');
    if (!response.ok()) {
      throw new Error(`Could not establish local test-user session: HTTP ${response.status()}`);
    }
    await context.storageState({ path: stateFile });
    await context.dispose();
    return;
  }

  if (!process.env.E2E_BASE_URL) {
    throw new Error('Authenticated UI tests require E2E_BASE_URL to point to the deployed test instance.');
  }

  const configuredState = process.env.E2E_STORAGE_STATE;
  if (!configuredState) {
    throw new Error('Authenticated UI tests require E2E_STORAGE_STATE to point to a Playwright authentication-state JSON file.');
  }

  const stateFile = path.resolve(process.cwd(), configuredState);
  if (!fs.existsSync(stateFile)) {
    throw new Error(
      `E2E_STORAGE_STATE does not exist: ${stateFile}\n` +
      'Replace the documentation placeholder with a real Playwright storage-state file for test user ID 4.',
    );
  }

  let state;
  try {
    state = JSON.parse(fs.readFileSync(stateFile, 'utf8'));
  } catch (error) {
    throw new Error(`E2E_STORAGE_STATE is not valid JSON: ${stateFile}\n${error.message}`);
  }

  if (!Array.isArray(state.cookies) || !Array.isArray(state.origins)) {
    throw new Error(`E2E_STORAGE_STATE is not a Playwright storage-state document: ${stateFile}`);
  }
};
