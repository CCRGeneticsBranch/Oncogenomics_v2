const { test, expect } = require('@playwright/test');
const path = require('path');
const { monitorPageHealth } = require('./page-health');
const { loadViewRoutes } = require('./view-route-catalog');

test.skip(process.env.RUN_INSTANCE_UI !== '1', 'Set RUN_INSTANCE_UI=1 and E2E_STORAGE_STATE to run live UI tests.');

const root = path.resolve(__dirname, '../..');
const fixtureFile = process.env.TEST_INSTANCE_FIXTURES;
if (!fixtureFile) throw new Error('Set TEST_INSTANCE_FIXTURES in .env.');

const fixtures = require(path.resolve(root, fixtureFile));
const exclusions = fixtures.view_route_exclusions || {};
const routes = loadViewRoutes(root, fixtures);
const testableRoutes = routes.filter(route => !exclusions[route.uri]);

test('every view route is tested or has a documented exclusion', async () => {
  expect(routes.length).toBeGreaterThanOrEqual(85);
  expect(testableRoutes.length + Object.keys(exclusions).length).toBe(routes.length);
  for (const [uri, reason] of Object.entries(exclusions)) {
    expect(reason.trim().length, `${uri} must have a meaningful exclusion reason`).toBeGreaterThanOrEqual(10);
  }
});

for (const route of testableRoutes) {
  test(`${route.uri} loads the authenticated view and exercises its core controls`, async ({ page }) => {
    const assertHealthy = monitorPageHealth(page);
    const response = await page.goto(route.path, { waitUntil: 'domcontentloaded' });

    expect(response, route.path).not.toBeNull();
    expect(response.status(), route.path).toBeLessThan(400);
    if (route.requiresLogin) {
      await expect(page).not.toHaveURL(/\/login(?:$|\?)/);
      await expect(page.locator('input[type="password"]')).toHaveCount(0);
    }
    await expect(page.locator('body')).toBeVisible();
    await expect(page.locator('body')).not.toBeEmpty();
    if (route.expectedSelector) {
      await expect(page.locator(route.expectedSelector),
        `${route.path} did not render its expected application view`).toBeVisible();
    }

    const visibleTabs = page.locator('.nav-tabs a:visible, [role="tab"]:visible');
    const tabCount = Math.min(await visibleTabs.count(), 8);
    for (let index = 0; index < tabCount; index += 1) {
      const tab = visibleTabs.nth(index);
      const href = await tab.getAttribute('href');
      if (href?.startsWith('#') && href.length > 1) {
        await tab.click();
        await expect(page.locator(href)).toBeVisible();
      }
    }

    const easyUiTabTitles = await page.locator('.tabs-header:visible .tabs-title:visible')
      .evaluateAll(elements => [...new Set(elements.map(element => element.textContent.trim()).filter(Boolean))].slice(0, 4));
    for (const title of easyUiTabTitles) {
      const exactTitle = new RegExp(`^${title.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`);
      const tab = page.locator('.tabs-header:visible .tabs-title:visible', { hasText: exactTitle }).first();
      if (!await tab.isVisible()) continue;
      await tab.click({ timeout: 5_000 });
      await expect(tab.locator('xpath=ancestor::li[1]')).toHaveClass(/tabs-selected/);
      await page.waitForTimeout(200);
    }

    const dataTables = page.locator('.dataTables_wrapper:visible');
    const dataTableCount = Math.min(await dataTables.count(), 4);
    for (let index = 0; index < dataTableCount; index += 1) {
      const wrapper = dataTables.nth(index);
      await expect(wrapper.locator('table')).toBeVisible();
      const search = wrapper.locator('input[type="search"]:visible').first();
      if (await search.count()) {
        await search.fill(fixtures.projects.primary.patients[0].patient_id);
        await search.fill('');
      }
    }

    const chartContainers = page.locator('.highcharts-container:visible');
    for (let index = 0; index < await chartContainers.count(); index += 1) {
      await expect(chartContainers.nth(index).locator('svg')).toBeVisible();
    }

    await assertHealthy();
  });
}
