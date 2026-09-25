const { test, expect } = require('@playwright/test');
const path = require('path');
const { monitorPageHealth } = require('./page-health');

test.skip(process.env.RUN_INSTANCE_UI !== '1', 'Set RUN_INSTANCE_UI=1 and E2E_STORAGE_STATE to run live UI tests.');

const fixtureFile = process.env.TEST_INSTANCE_FIXTURES;
if (!fixtureFile) {
  throw new Error('Set TEST_INSTANCE_FIXTURES in .env to an instance fixture JSON file.');
}

const fixtures = require(path.resolve(process.cwd(), fixtureFile));

const primary = fixtures.projects.primary;
const patient = primary.patients[0];
const caseFixture = patient.cases[0];
const cancerType = fixtures.cancer_types.primary;

const journeys = [
  [`project ${primary.id}`, `/viewProjectDetails/${primary.id}`],
  [`patient ${patient.patient_id}`, `/viewPatient/${primary.id}/${patient.patient_id}/${caseFixture.case_id}`],
  [`case ${caseFixture.case_id}`, `/viewCase/${primary.id}/${patient.patient_id}/${caseFixture.case_id}`],
  ['case QC', `/viewVarQC/${primary.id}/${patient.patient_id}/${caseFixture.case_id}`],
  ['cancer-type list', '/viewCancerTypes'],
  [`cancer type ${cancerType.name}`, `/viewCancerTypeDetails/${cancerType.id}/Y`],
  ['cancer-type expression', `/viewCancerTypeExpression/${cancerType.id}/null/null/null/null/Y`],
  ['cancer-type somatic mutations', `/viewVarCancerTypeDetail/${cancerType.id}/somatic/Y`],
  ['cancer-type fusions', `/viewFusionCancerTypeDetail/${cancerType.id}/Y`],
  ['cancer-type QC', `/viewCancerTypeQC/${cancerType.id}/Y`],
  ['cancer-type TIL', `/viewCancerTypeTIL/${cancerType.id}/Y`],
  ['cancer-type ChIP-seq', `/viewCancerTypeChIPseq/${cancerType.id}/Y`],
];

for (const [name, path] of journeys) {
  test(`${name} renders without JavaScript or HTTP 5xx errors`, async ({ page }) => {
    const assertHealthy = monitorPageHealth(page);
    const response = await page.goto(path, { waitUntil: 'domcontentloaded' });

    expect(response).not.toBeNull();
    expect(response.status()).toBeLessThan(400);
    await expect(page).not.toHaveURL(/\/login(?:$|\?)/);
    await expect(page.locator('body')).toBeVisible();
    await assertHealthy();
  });
}

test('primary project summary endpoint returns a successful data response', async ({ request }) => {
  const response = await request.get(`/getProjectSummary/${primary.id}`);
  expect(response.ok()).toBeTruthy();
  expect((await response.text()).trim()).not.toBe('');
});

test('cancer-type catalog contains the configured cancer type', async ({ request }) => {
  const response = await request.get('/getCancerTypes');
  expect(response.ok()).toBeTruthy();

  const payload = await response.json();
  expect(payload.cols).toBeInstanceOf(Array);
  expect(payload.data).toBeInstanceOf(Array);
  expect(JSON.stringify(payload.data)).toContain(cancerType.name);
});

test('cancer-type summary returns fusion and patient metadata', async ({ request }) => {
  const response = await request.get(`/getCancerTypeSummary/${cancerType.id}/Y`);
  expect(response.ok()).toBeTruthy();

  const payload = await response.json();
  expect(payload.fusion).toBeInstanceOf(Array);
  expect(payload.patient_meta).toBeTruthy();
});

test('cancer-type sample endpoint returns tabular data', async ({ request }) => {
  const response = await request.get(`/getCancerTypeSamples/${cancerType.id}/json/all/Y`);
  expect(response.ok()).toBeTruthy();

  const payload = await response.json();
  expect(payload.cols).toBeInstanceOf(Array);
  expect(payload.data).toBeInstanceOf(Array);
  expect(payload.data.length).toBeGreaterThan(0);
});
