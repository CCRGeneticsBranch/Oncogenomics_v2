const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { monitorPageHealth } = require('./page-health');

test('login page renders without browser or server errors', async ({ page }) => {
  const assertHealthy = monitorPageHealth(page);
  const response = await page.goto('/login');

  expect(response).not.toBeNull();
  expect(response.status()).toBeLessThan(500);
  await expect(page.locator('body')).toBeVisible();
  await assertHealthy();
});

test('login page has no serious accessibility violations', async ({ page }) => {
  await page.goto('/login');
  const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
  const serious = results.violations.filter(item => ['serious', 'critical'].includes(item.impact));
  expect(serious, JSON.stringify(serious, null, 2)).toEqual([]);
});

const protectedPaths = [
  '/viewChatbot',
  '/getProjects',
  '/viewProjects',
  '/viewProjectDetails/22112',
  '/viewCase/22112/CL0039/OM17-047',
  '/viewVarQC/22112/CL0039/OM17-047',
  '/viewIGV/CL0039/CL0039_T1D_E_HNGYYBGX2/OM17-047/somatic/1/chr1:1-100/hg19',
];

for (const path of protectedPaths) {
  test(`guest is redirected from ${path}`, async ({ page }) => {
    const response = await page.goto(path);
    expect(response).not.toBeNull();
    await expect(page).toHaveURL(/\/login(?:$|\?)/);
    await expect(page.locator('body')).toBeVisible();
  });
}
