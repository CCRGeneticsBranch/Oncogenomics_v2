const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const path = require('path');

test.skip(process.env.RUN_ACCESSIBILITY_UI !== '1',
  'Set RUN_ACCESSIBILITY_UI=1 to run the separately reported accessibility audit.');

const fixtureFile = process.env.TEST_INSTANCE_FIXTURES;
if (!fixtureFile) throw new Error('Set TEST_INSTANCE_FIXTURES in .env.');

const fixtures = require(path.resolve(process.cwd(), fixtureFile));
const project = fixtures.projects.primary;
const patient = project.patients[0];
const caseFixture = patient.cases[0];
const cancerType = fixtures.cancer_types.primary;

const representativeViews = [
  ['project', `/viewProjectDetails/${project.id}`],
  ['patient', `/viewPatient/${project.id}/${patient.patient_id}/${caseFixture.case_id}`],
  ['case', `/viewCase/${project.id}/${patient.patient_id}/${caseFixture.case_id}`],
  ['cancer type', `/viewCancerTypeDetails/${cancerType.id}/Y`],
];

for (const [name, url] of representativeViews) {
  test(`${name} view has no serious or critical WCAG A/AA violations`, async ({ page }) => {
    const response = await page.goto(url, { waitUntil: 'domcontentloaded' });

    expect(response).not.toBeNull();
    expect(response.status()).toBeLessThan(400);
    await expect(page).not.toHaveURL(/\/login(?:$|\?)/);

    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa'])
      .disableRules(['color-contrast'])
      .analyze();
    const severe = results.violations.filter(item => ['serious', 'critical'].includes(item.impact));

    expect(severe, JSON.stringify(severe, null, 2)).toEqual([]);
  });
}
