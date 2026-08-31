const { test, expect } = require('@playwright/test');
const path = require('path');
const { monitorPageHealth } = require('./page-health');

test.skip(process.env.RUN_INSTANCE_UI !== '1', 'Set RUN_INSTANCE_UI=1 and E2E_STORAGE_STATE to run live UI tests.');

const fixtureFile = process.env.TEST_INSTANCE_FIXTURES;
if (!fixtureFile) throw new Error('Set TEST_INSTANCE_FIXTURES in .env.');

const fixtures = require(path.resolve(process.cwd(), fixtureFile));
const project = fixtures.projects.primary;
const patient = project.patients[0];
const caseFixture = patient.cases[0];
const caseUrl = `/viewCase/${project.id}/${patient.patient_id}/${caseFixture.case_id}`;

const capabilityTabs = {
  germline: 'Germline',
  somatic: 'Somatic',
  hotspot: 'Hotspot',
  rnaseq: 'RNAseq',
  fusion: 'Fusion',
  cnv: 'CNV',
  expression: 'Expression',
  qc: 'QC',
};

async function openCase(page) {
  const assertHealthy = monitorPageHealth(page);
  const response = await page.goto(caseUrl, { waitUntil: 'domcontentloaded' });
  expect(response).not.toBeNull();
  expect(response.status()).toBeLessThan(400);
  await expect(page).not.toHaveURL(/\/login(?:$|\?)/);
  await expect(page.locator('#tabVar')).toBeVisible();
  return assertHealthy;
}

test('case identity, pipeline metadata, and summary data render', async ({ page }) => {
  const assertHealthy = await openCase(page);

  await expect(page.locator('body')).toContainText(patient.patient_id);
  await expect(page.locator('body')).toContainText(caseFixture.case_id);
  await expect(page.locator('#pipline_version')).not.toBeEmpty();
  await expect(page.locator('#genome_version')).not.toBeEmpty();
  await expect(page.locator('#case_summary tbody tr').first()).toBeVisible({ timeout: 20_000 });

  await assertHealthy();
});

test('every capability-backed case tab opens without failed data requests', async ({ page }) => {
  const assertHealthy = await openCase(page);
  const expectedTabs = ['Summary', ...new Set(
    caseFixture.capabilities.map(capability => capabilityTabs[capability]).filter(Boolean),
  )];

  for (const title of expectedTabs) {
    const exactTitle = new RegExp(`^${title.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`);
    const tabTitle = page.locator('#tabVar > .tabs-header .tabs-title', { hasText: exactTitle }).first();
    await expect(tabTitle, `Missing ${title} tab`).toBeVisible();
    await tabTitle.click();
    await expect(tabTitle.locator('xpath=ancestor::li[1]')).toHaveClass(/tabs-selected/);
    await page.waitForTimeout(300);
  }

  await assertHealthy();
});

test('case page tables and safe navigation controls initialize', async ({ page }) => {
  const assertHealthy = await openCase(page);

  const summary = page.locator('#case_summary');
  await expect(summary).toBeVisible();
  await expect(summary.locator('tbody tr').first()).toBeVisible({ timeout: 20_000 });

  const links = page.locator('a[href]:visible');
  expect(await links.count()).toBeGreaterThan(0);
  const duplicateIds = await page.locator('[id]').evaluateAll(elements => {
    const counts = new Map();
    for (const element of elements) counts.set(element.id, (counts.get(element.id) || 0) + 1);
    return [...counts.entries()].filter(([id, count]) => id && count > 1);
  });
  expect(duplicateIds, `Duplicate element IDs: ${JSON.stringify(duplicateIds)}`).toEqual([]);

  await assertHealthy();
});
