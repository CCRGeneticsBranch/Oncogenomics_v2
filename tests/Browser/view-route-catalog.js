const fs = require('fs');
const path = require('path');

function loadJson(file) {
  return JSON.parse(fs.readFileSync(file, 'utf8'));
}

function fixtureContext(fixtures) {
  const project = fixtures.projects.primary;
  const patient = project.patients[0];
  const caseFixture = patient.cases[0];
  const browser = fixtures.browser_values || {};

  return {
    project,
    patient,
    caseFixture,
    cancerType: fixtures.cancer_types.primary,
    browser,
    values: {
      project_id: project.id,
      project_name: project.name,
      patient_id: patient.patient_id,
      case_id: caseFixture.case_id,
      cancer_type_id: fixtures.cancer_types.primary.id,
      sample_id: browser.rnaseq_sample_id || browser.exome_sample_id,
      sample_name: browser.rnaseq_sample_name || browser.rnaseq_sample_id,
      gene_id: browser.gene || 'PTEN',
      gid: browser.gene || 'PTEN',
      symbol: browser.gene || 'PTEN',
      cohort_id: project.id,
      cohort_type: 'project',
      include_public: 'Y',
      include_header: 'Y',
      with_header: 'Y',
      has_header: 'Y',
      custom_id: patient.patient_id,
      sid: project.id,
      search_text: patient.patient_id,
      keyword: patient.patient_id,
      type: 'somatic',
      source: 'somatic',
      genome: 'hg19',
      locus: browser.locus || 'chr10:89623000-89624000',
      center: browser.variant?.start || 89623195,
      chromosome: browser.variant?.chromosome || 'chr10',
      chr: browser.variant?.chromosome || 'chr10',
      start: browser.variant?.start || 89623195,
      end: browser.variant?.end || 89623195,
      ref: browser.variant?.ref || 'A',
      alt: browser.variant?.alt || 'G',
      gene: browser.variant?.gene || browser.gene || 'PTEN',
      left_gene: browser.fusion?.left_gene || 'ABHD17B',
      right_gene: browser.fusion?.right_gene || 'TMEM2(CEMIP2)',
      left_chr: browser.fusion?.left_chr || 'chr9',
      left_position: browser.fusion?.left_position || 74477467,
      right_chr: browser.fusion?.right_chr || 'chr9',
      right_position: browser.fusion?.right_position || 74365301,
      cutoff: '0.1',
      call_type: 'all',
      content_cat: 'qc',
      content_type: 'html',
      suffix: 'qc',
      rose_type: 'all',
      setting: 'default',
      meta_type: 'null',
      diagnosis: 'null',
      diag: 'null',
      value: 'null',
      show_search: 'Y',
      path: 'index.html',
      target: 'all',
      token_id: browser.gsea_token || 'missing-test-token',
      file_name: browser.upload_annotation_file || 'missing-test-file',
      id: browser.biomaterial_id || patient.patient_id,
    },
  };
}

function routeOverrides(uri, context) {
  const { project, patient, caseFixture, browser } = context;
  const overrides = {
    'viewCase/{project_name}/{patient_id}/{case_id}/{with_header?}': { project_name: project.id },
    'viewPatient/{project_name}/{patient_id}/{case_id?}': { project_name: project.id },
    'viewIGV/{patient_id}/{sample_id}/{case_id}/{type}/{center}/{locus}/{genome?}': {
      sample_id: browser.exome_sample_id,
      type: 'somatic',
    },
    'viewFusionIGV/{patient_id}/{sample_id}/{case_id}/{left_chr}/{left_position}/{right_chr}/{right_position}': {
      sample_id: browser.rnaseq_sample_id,
    },
    'viewExpressionByCase/{project_id}/{patient_id}/{case_id}/{sample_id?}': {
      sample_id: browser.rnaseq_sample_id,
    },
    'viewVarAnnotation/{project_id}/{patient_id}/{sample_id}/{case_id}/{type}': {
      sample_id: browser.exome_sample_id,
    },
    'viewVariant/{patient_id}/{case_id}/{sample_id}/{type}/{chr}/{start}/{end}/{ref}/{alt}/{gene}/{genome?}/{source?}': {
      sample_id: browser.exome_sample_id,
    },
    'viewAntigen/{project_id}/{patient_id}/{case_id}/{sample_name}': {
      sample_name: browser.exome_sample_name,
    },
    'viewCNV/{project_id}/{patient_id}/{case_id}/{sample_name}/{source}/{gene_centric?}': {
      sample_name: browser.exome_sample_name,
      source: 'cnvkit',
    },
    'viewCNVGeneLevel/{patient_id}/{case_id}/{sample_name}/{source}': {
      sample_name: browser.exome_sample_name,
      source: 'cnvkit',
    },
  };

  return overrides[uri] || {};
}

function compileRoute(uri, context, includeOptional = false) {
  const values = { ...context.values, ...routeOverrides(uri, context) };
  const segments = uri.split('/').filter(Boolean);
  const compiled = [];

  for (const segment of segments) {
    const match = segment.match(/^\{([^}?]+)(\?)?\}$/);
    if (!match) {
      compiled.push(segment);
      continue;
    }

    const [, key, optional] = match;
    if (optional && !includeOptional) continue;
    const value = values[key];
    if (value === undefined || value === null || value === '') {
      throw new Error(`No browser fixture value for {${key}} in ${uri}`);
    }
    compiled.push(encodeURIComponent(String(value)));
  }

  return `/${compiled.join('/')}`;
}

function loadViewRoutes(root, fixtures) {
  const routes = loadJson(path.join(root, 'tests/Fixtures/routes.json'));
  const context = fixtureContext(fixtures);

  return routes
    .filter(route => route.method.includes('GET') && /^view/i.test(route.uri))
    .map(route => ({
      ...route,
      path: compileRoute(route.uri, context),
      requiresLogin: route.middleware.some(item => item.endsWith('\\Logged')),
      expectedSelector: {
        'viewProjectDetails/{project_id}': '#tabDetails',
        'viewPatient/{project_name}/{patient_id}/{case_id?}': '#tabCases',
        'viewCase/{project_name}/{patient_id}/{case_id}/{with_header?}': '#tabVar',
        'viewVarQC/{project_id}/{patient_id}/{case_id}': '#tabQC',
      }[route.uri],
    }));
}

module.exports = { compileRoute, fixtureContext, loadViewRoutes };
