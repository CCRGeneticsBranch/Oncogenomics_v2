# Authorized SQL fallback

The chatbot now exposes `getSqlSchema` and `runAuthorizedSql`. Existing domain tools
remain preferred. SQL is for an unsupported filter, join or aggregation, not a way
to override an empty result. The agent must provide a `capability_gap` explanation;
that explanation is a routing requirement, not a machine proof of tool insufficiency.

## Execution boundary

The model supplies SQL and scalar parameter values. `AuthorizedSelect` tokenizes
and parses a limited SELECT grammar, verifies columns against the approved catalog,
checks authorization, and compiles a new SQL statement. It never executes the model
string verbatim and does not use a keyword blacklist as its security boundary.

Every accepted query must contain exactly one `user_projects` alias and a mandatory
AND-connected `u.user_id = :auth_user_id` condition. The backend supplies this user
ID from authentication; model bindings may only be named p1, p2, etc. Each protected
source must have its full direct authorization join:

- Project membership and expression relations: project_id → user_projects.project_id.
- Project catalog: projects.id → user_projects.project_id.
- Samples, mutation annotations, tiers and fusions: patient_id AND sample_id → one
  authorized project_samples alias.
- Pathogenic variants: patient_id AND case_id → the same authorized project_cases alias.
- Patients and patient_details: patient_id → authorized project_patients.
- sample_details: sample_id → authorized project_samples (user confirmed unique sample IDs).

Additional analytical join keys are still necessary. Authorization validation does
not establish correct biological interpretation or mutation-event deduplication.
All supplied mutation indexes are nonunique.

Fixed project scope adds a server-owned project predicate. Fixed cancer-type scope
adds a diagnosis predicate to every patient/sample membership alias; it excludes
project_values, project_processed_cases and project_cases because project authorization alone
would not restrict their rows to that diagnosis. Omitting cohort parameters in a
global query includes only projects authorized by user_projects.

## Accepted SQL

A single SELECT, optional DISTINCT, qualified columns with unique AS output aliases,
INNER JOIN with AND-connected column equalities, WHERE with mandatory authorization,
optional GROUP BY, HAVING and ORDER BY. Functions: COUNT (including DISTINCT or *),
SUM, AVG, MIN, MAX, UPPER, LOWER, SAFE_NUMBER (also inside aggregates). Filters: comparisons, LIKE, IN and IS [NOT] NULL,
connected by AND. All filter values must use parameter bindings.

Rejected: writes, DDL, string/numeric literals in SQL, comments, semicolons, SELECT *,
subqueries, CTEs, UNION, OR, outer/cross joins, arbitrary functions, schema-qualified
objects, database links, arithmetic, and caller-supplied LIMIT. Unsupported queries
are rejected, not partially executed or automatically weakened.

Example invocation:

```json
{
  "sql": "SELECT ps.library_type AS capture_method, COUNT(DISTINCT ps.sample_id) AS samples FROM project_samples ps INNER JOIN user_projects u ON ps.project_id = u.project_id WHERE u.user_id = :auth_user_id AND ps.exp_type = :p1 GROUP BY ps.library_type ORDER BY samples DESC",
  "parameters": [{"name": "p1", "value": "Exome"}],
  "cohort_type": "project",
  "cohort_id": "24521",
  "capability_gap": "The available tools do not expose this capture-method sample-count aggregation.",
  "limit": 100
}
```

Parameter values can contain punctuation safely; they are never interpolated into
SQL. Technical details show the actual bounded SQL with positional placeholders
and the corresponding ordered bindings, including the server-bound identity/scope.

## Database and resource limits

The executor creates an isolated connection using `CHATBOT_SQL_CONNECTION`, or the
application default if unset. It starts a read-only transaction, executes a bounded
SELECT, rolls back, and disconnects. No persistent views or schema changes are needed.
A database account restricted to SELECT remains useful defense in depth when available.

Supported production drivers:

- Oracle: SET TRANSACTION READ ONLY and OCI call timeout (required). The timeout is
  per database round trip, not a guaranteed total request wall-clock bound.
- MySQL: read-only transaction and MAX_EXECUTION_TIME. A server that does not support
  these settings fails closed. MySQL execution has not been live-tested here.

SQLite is permitted only in the testing environment. Defaults: 5,000 ms database
execution/call limit, 100 returned rows; hard maximum 500 rows, 1 MB returned row
JSON, and 4,000 characters per cell. The SELECT fetches one extra row to detect row
truncation. Results identify truncation; row_count is returned rows, not total matches.
Large database values may still incur transfer cost before truncation.

Environment controls:

- CHATBOT_SQL_ENABLED=false disables execution.
- CHATBOT_SQL_CONNECTION selects an existing configured database connection.
- CHATBOT_SQL_TIMEOUT_MS sets the timeout, clamped to 100–30,000 ms.

Normal conversation storage includes SQL details. Existing cache expiry/evidence
pruning can remove old details; the persistent query log currently records the tool
name, not a separate durable SQL audit trail. Execution errors are shown as errors,
never as proof of no data; database exception text/bindings are not exposed.

## Verification and limits

Adversarial parser tests reject unauthorized/malformed joins and unsupported SQL.
Multi-user SQLite tests include a shared patient with differently authorized samples,
unauthorized fixed projects, bound injection strings, and users with no memberships.
An Oracle smoke test used an empty-scope query and verified the read-only/timeout path.
No production data was modified.

This is a constrained SQL capability, not a claim of safety for arbitrary generated
SQL. SQL grammar and table additions require authorization tests and review. Domain
filter correctness, plan cost, and schema drift still need operational review.
Survival times are days; VAF and MAF are fractions from 0 to 1.

## Numeric metadata and final results

`patient_details.attr_value` remains text even when `type=number`. Use
`SAFE_NUMBER(pd.attr_value)` in selected numeric output and filters. The server
renders this as a guarded numeric conversion for Oracle/MySQL; invalid/missing
values become NULL. Supported decimals have up to 18 integer and 12 fractional
digits, with an optional sign. Scientific notation is currently excluded.
Numeric comparison parameters are converted too. Raw range comparisons on
metadata attr_value are rejected, as are raw text/LIKE comparisons when the query
identifies numeric metadata. LIKE is never a substitute for a threshold.

For overall survival, filter class=overall_survival and type=number using bindings.
The class identifies the role even when attr_name varies; no preliminary attribute
query is needed. Global questions default to all authorized projects. A patient
list should select DISTINCT patient/value pairs without project columns unless
requested, preserving conflicting observations instead of inventing one value.

Mark SQL calls `result_role=discovery` for necessary metadata exploration and
`result_role=answer` for final results. Discovery and superseded attempts stay in
collapsed Technical details. Only the latest answer SQL attempt can supply the
main table; a failed final attempt does not promote an earlier result. Older saved
conversations also show only their last SQL result in the main area. Truncation is
preserved in both agent previews and display tables. Empty final responses trigger
one tool-free evidence synthesis attempt, then an explicit incomplete-analysis
message if synthesis fails; recovery cannot run more SQL.

## Database type supplied to the model

The agent instructions, compatibility-selector prompt, `getSqlSchema.database`
response, and SQL tool contract include the actual resolved `db_type` (`mysql` or
`oracle`). Resolution uses `CHATBOT_SQL_CONNECTION` when set, otherwise the default
Laravel database connection, including any DATABASE_URL override. Execution uses
that same resolved configuration. Credentials, hosts and database names are not
included in model context, and the model cannot choose a connection or dialect.

For MySQL, the server renders SAFE_NUMBER as a guarded CAST AS DECIMAL(30,12),
uses LIMIT and MAX_EXECUTION_TIME, and starts a read-only transaction. The model
still generates the restricted parameterized SELECT grammar, not native casts,
ROWNUM, TO_NUMBER or LIMIT clauses. Oracle catalog type labels are explicitly
identified as reference metadata, not instructions to generate Oracle syntax.
The MySQL path is covered by mocked PDO execution tests; no live MySQL server has
been tested in this workspace. Use a configured MySQL connection with the same
allowlisted application schema. No database migration is required for this change.

## Failed-query diagnostics

SQL arguments retain up to the accepted 16,000 characters rather than the general
500-character argument preview. Execution failures retain the attempted statement,
dialect, positional bindings (when prepared), row/timeout limits, execution stage,
a classified database code such as ORA-01013, and an error reference. The UI labels
these as attempted SQL. Logs record the reference and classified diagnostic, not
the raw driver message or connection credentials. Older generic failures cannot
be retrospectively classified because their original error codes were not stored.
An empty-scope smoke test checks syntax and schema access, not performance on real
authorized records; a timeout must be diagnosed from the actual failed execution.
