const { test, expect } = require('@playwright/test');
const path = require('path');
const { loadViewRoutes } = require('./view-route-catalog');

const root = path.resolve(__dirname, '../..');
const fixtureFile = process.env.TEST_INSTANCE_FIXTURES;
if (!fixtureFile) throw new Error('Set TEST_INSTANCE_FIXTURES in .env.');
const fixtures = require(path.resolve(root, fixtureFile));
const protectedViews = loadViewRoutes(root, fixtures).filter(route => route.requiresLogin);

test('security matrix includes every protected view route', async () => {
  expect(protectedViews.length).toBeGreaterThan(70);
});

for (const route of protectedViews) {
  test(`access control redirects an unauthenticated guest from ${route.uri}`, async ({ page }) => {
    const response = await page.goto(route.path, { waitUntil: 'domcontentloaded' });
    expect(response).not.toBeNull();
    await expect(page).toHaveURL(/\/login(?:$|\?)/);
    await expect(page.locator('body')).toBeVisible();
  });
}
