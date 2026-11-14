# Clinomics schema description for LLM queries

This is a reviewable data dictionary, not an authorization mechanism. The new
[SQL fallback](sql-fallback.md) uses a separately defined table allowlist and
restricted parser to enforce access. `clinomics-data-dictionary.json` contains the inspected
column names, database types, nullability, and draft semantic descriptions.

The dictionary covers 14 relations: 11 inspected from live column metadata and
three added from user-supplied DDL (`project_processed_cases`,
`var_avia_cols_oc_all`, and `project_values`). Each relation records its provenance.
User confirmations refine field meanings; unreviewed annotation fields remain
marked `needs_review`. No patient/sample records or distinct value lists are included.
The inspection timestamp describes the original metadata snapshot, not a fresh
verification of the user-supplied DDL or indexes.

## Core relations

| Relation | Purpose | Authorization path |
| --- | --- | --- |
| user_projects | User/project memberships | Backend-bound authenticated user ID |
| projects | Project names and settings | id → user_projects.project_id |
| project_samples | Samples belonging to projects, with metadata | project_id → user_projects.project_id |
| project_patients | Patients belonging to projects | project_id → user_projects.project_id |
| samples | Sample metadata | (patient_id, sample_id) → authorized project_samples |
| patients | Patient metadata | patient_id → authorized project_patients |
| sample_details | Additional sample attributes | sample_id → unique samples.sample_id → authorized project_samples |
| patient_details | Additional patient attributes | patient_id → authorized project_patients |
| Configured mutation relation | Variant annotations; currently recorded in the JSON | (patient_id, sample_id) → authorized project_samples |
| var_tier_avia | Germline/somatic tier annotations | (patient_id, sample_id) → authorized project_samples |
| var_fusion | Fusion partner and tier/caller information | (patient_id, sample_id) → authorized project_samples |
| project_processed_cases | Project/case membership | project_id → user_projects.project_id; case records link by patient_id AND case_id |
| project_values | RNA expression vectors and ordered sample lists | project_id → user_projects.project_id |
| var_avia_cols_oc_all | Mutation annotation column descriptions | Schema reference; validate returned column names before using them |

These are relationships observed in application code, not verified primary-key or
foreign-key constraints. Tables and views are collectively called relations here;
view definitions have not been inspected. Confirm them before approving generated
SQL. A project may override the tier or fusion relation used by a workflow.

## Important field meanings

- `library_type` is the capture method or assay/library designation, as confirmed
  by the user. It has many project-dependent values. For RNA it describes preparation (e.g. `polyA`); for Exome it describes capture;
  for ChIPseq it is the pull-down protein/target (e.g. MYCN or H3K27ac).
  Do not impose one universal enum.
- `tissue_type` is the sample-level Tissue Type field used for diagnosis coloring.
  For normal samples, Tissue Type is the organ.
  `diagnosis` is a patient/cohort field. They are related concepts, not necessarily
  identical values. `tissue_cat` is a separate broad category such as tumor/normal.
- Mutation `type` selects the call source: `somatic` means paired tumor/normal,
  `variants` means tumor-only, and `rnaseq` means RNA-seq. The MCP API spelling
  `ranseq` maps to `rnaseq`. These are application-recognized values, not a distinct
  database-value census.
- `somatic_level` and `germline_level` specify tier annotation contexts. Tumor-only
  calls may have somatic tiers without being confirmed paired somatic calls.
- Fusion `var_level` uses a different encoding: existing code reads its first
  character as the tier number. Mutation tools recognize `Tier 1`–`Tier 4` prefixes.
- Existing mutation gene summaries count distinct patients. A request for “most
  mutations” needs an explicit counting unit; do not label patient counts as events.

## Supplying context to an LLM

Provide the rules and only the relevant table/column sections for a question,
not every annotation column on every turn. Resolve the configured mutation relation on the
server. Descriptions are reference data and must never grant access.

For high-cardinality fields such as capture method, discover exact values and
counts within the authenticated user's authorized project scope, with search,
limits and pagination. Authorized `SELECT DISTINCT` queries through the SQL fallback can discover values;
a separate getFieldValues tool is not implemented. Do not guess stored spelling or present an incomplete
list as exhaustive.

## Authorization example

This illustrates the intended sample authorization path. It is not a SQL validator.
`mutation_relation` is a placeholder for a backend-approved configured relation.

```sql
SELECT v.patient_id, v.sample_id, v.gene
FROM mutation_relation v
WHERE EXISTS (
    SELECT 1
    FROM project_samples ps
    JOIN user_projects up ON up.project_id = ps.project_id
    WHERE up.user_id = :authenticated_user_id
      AND ps.patient_id = v.patient_id
      AND ps.sample_id = v.sample_id
)
```

Bind the user ID from the session. Apply any requested project restriction in
addition to this access restriction. Validate every protected source/branch and
exclude unauthorized records before aggregation. Membership joins may duplicate
rows across projects; `EXISTS` avoids that particular multiplication. It does not
deduplicate underlying mutation observations.

## Review still needed

1. Physical constraints and view definitions. User confirms sample_id is unique in
   samples and is formed as sample_name + underscore + run_id; project membership
   rows are not thereby unique.
2. Approved variant-to-tier join keys. Existing paths differ on case, gene and
   sample matching; genome-build compatibility also needs review.
3. Coordinate conventions. Survival time units are confirmed as days. VAF and MAF are confirmed
   fractions on a 0–1 scale (5% = 0.05).
4. Variant annotation columns marked `needs_review`.
5. False-positive exclusions, project-specific source overrides, and counting rules.
6. Approved database connection, read-only permissions and SQL validation policy.
7. Exact expression-vector/sample-list matching dimensions and missing-value encoding.
8. Whether case access ever replaces sample access; until reviewed, retain sample
   restrictions on sample-bearing records as well as case membership where needed.

Expression matrices and PCA coordinates are also file-backed in this application;
SQL alone does not cover every existing tool capability.


## Confirmed case and expression rules

Case records link to `project_processed_cases` using **patient_id AND case_id**.
That membership alone does not authorize access: also correlate its project_id to
`user_projects.project_id` and filter by the backend-bound authenticated user ID.
A requested project ID is an additional restriction, not a replacement for access.

In `project_values`, **TARGET='_list'** identifies the comma-separated sample-ID
list. Gene VALUE_LIST entries follow exactly the same positional order. Do not use
SYMBOL to identify the list row, sort one list independently, or discard empty
entries before alignment. Verify equal lengths. The dimensions used to pair a gene
row with its correct sample-list row, and missing-value encoding, still need review.
Both sample-list and expression rows require project authorization.

## Supplied mutation indexes

These are user-supplied Oracle index definitions for `var_sample_avia_oc`, owned by
`OS_ADMIN`, reported VALID and NORMAL. **All five are NONUNIQUE**; even the name
ending `_PK` does not establish uniqueness or a primary-key constraint.

| Index name | Columns, in index order |
| --- | --- |
| VAR_SAMPLE_AVIA_OC_PK | TYPE, PATIENT_ID, CASE_ID, SAMPLE_ID, CHROMOSOME, START_POS, END_POS, REF, ALT |
| VAR_SAMLE_AVIA_OC_COORD | CHROMOSOME, START_POS, END_POS, REF, ALT |
| VAR_SAMPLE_AVIA_OC_GENE | TYPE, GENE, CANONICALPROTPOS |
| VAR_SAMPLE_AVIA_OC_SAMPLE | SAMPLE_ID |
| VAR_SAMPLE_AVIA_OC_PATIENT | TYPE, PATIENT_ID, CASE_ID, SAMPLE_ID |

Keep the supplied spelling `VAR_SAMLE_AVIA_OC_COORD`. Source-plus-gene filters,
source-plus-patient/case filters, sample filters, and coordinate lookups have
corresponding candidate access paths. Index availability does not guarantee their
use: inspect execution plans and enforce runtime limits. Broad gene rankings can
still scan many authorized records. Do not infer mutation counts from row counts
or uniqueness from these index names.
