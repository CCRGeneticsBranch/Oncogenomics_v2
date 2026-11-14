# Weekly OncoTree mapping

Run from the Clinomics application directory:

```bash
php artisan oncotree:update-mappings --dry-run --limit=1
php artisan oncotree:update-mappings
```

The first command researches and validates but does not update the database,
or change retry state. It emails the preview only to `chouh@nih.gov`, even in
production mode. The second inserts supported mappings and
sends a summary. **Dev mode changes the email recipients only; it still updates
the database unless `--dry-run` is supplied.**

## Inputs and behavior

- `docs/oncotree.tsv` is authoritative; `ID` is the accepted OncoTree code.
- Find distinct `cancer_types.ID` values with no exact matching
  `oncotree_mapping.DIAGNOSIS`. Pass only ID/name/description to Codex, not patients.
- Research against the local taxonomy using live web search. Sources are limited
  to PubMed/PMC, NCI, and official OncoTree. Return a code, rationale, and exact
  supporting quotations. Unsupported/ambiguous diagnoses remain unmapped.
- Independently fetch each cited HTTPS source, with redirects disabled, and verify
  that the quoted passage exists. This checks evidence provenance, not clinical
  correctness; read the weekly report to catch misinterpretations.
- Insert only `DIAGNOSIS = original cancer_types.ID`, `ID = validated TSV code`.
  No existing mapping is overwritten, including mappings to blank/obsolete codes;
  those need a separate correction process. No normalization changes the join key.
- Check the diagnosis still exists unchanged before insertion. Use bound queries,
  one transaction per insert, and database-specific locking: Oracle takes a short
  exclusive table lock; MySQL uses a database-specific named lock on the writer
  connection and releases it after commit or rollback. Busy locks cause an
  error/retry, not a long-held lock during LLM research.
- Process all eligible unmapped terms per run by default. Use `--limit=20` for
  a smaller batch, or `--limit=0` to explicitly select unlimited processing. New terms are prioritized;
  failed terms are retried later. Ambiguous/unresolved terms wait 30 days unless
  their input or taxonomy changes. `--retry-unresolved` bypasses this cooldown.
- Run on only one host per database. Local flock prevents overlapping cron runs
  on that host; database locks coordinate this job across application hosts.
  MySQL named locks coordinate only cooperating jobs on the same database server;
  they do not block unrelated manual SQL or coordinate multi-primary clusters.

## Configuration

Defaults are in `config/oncotree_mapping.php`:

```dotenv
ONCOTREE_MAPPING_MODE=dev
ONCOTREE_CODEX_BINARY=/absolute/path/to/codex
# Optional: pin an available model for reproducible runs.
ONCOTREE_CODEX_MODEL=
ONCOTREE_CODEX_REASONING_EFFORT=high
ONCOTREE_CODEX_TIMEOUT=1200
ONCOTREE_MAPPING_LIMIT=0
ONCOTREE_MAPPING_RETRY_DAYS=30
```

In **dev**, the only recipient is **chouh@nih.gov**. It is intentionally not
inferred from APP_ENV. Production is opt-in with `ONCOTREE_MAPPING_MODE=production`.
Production recipients are deduplicated valid `users.email` values whose parsed
permissions JSON has `_superadmin` or `_projectmanager` enabled (`1`, `"1"`, true).
Recipients receive separate messages; no distribution list is exposed.
Do not switch to production until the requested development review is complete.
If configuration is cached, rebuild the configuration cache after changes using
your normal deployment procedure.

## Cron setup

The cron account needs the same working database/mail configuration as Clinomics,
write access to `storage/app/oncotree-mapping`, and a working Codex login. Use the
absolute PHP and Codex paths appropriate to that account; check `command -v php`
and `command -v codex`. Check `codex login status` as that exact account.
The implementation uses Codex CLI `exec`, `--search`, `--ignore-user-config`,
`--output-schema`, and `--ephemeral`; verified against installed CLI 0.147.0.

Example crontab for this server account (Sunday 03:15 **server time**):

```cron
PATH=/home/ncif-www-onc-svc/.local/bin:/usr/local/bin:/usr/bin:/bin
15 3 * * 0 cd /var/www/html/clinomics && /usr/bin/php artisan oncotree:update-mappings --no-interaction >> /var/www/html/clinomics/storage/logs/oncotree-mapping-cron.log 2>&1
```

Run this crontab as `ncif-www-onc-svc`, whose Codex login was verified.
For other accounts or instances, adjust the paths and provision that account's own login.

Set the host/cron timezone deliberately if you require America/New_York; cron
implementations differ in their support for per-job timezone declarations.
No cron entry is installed automatically by adding these files.

Codex is invoked with a read-only sandbox and ignores the user's config to avoid
loading unrelated MCP servers. Database/mail environment variables are removed
from its subprocess environment. CLI authentication uses the cron account's
existing HOME/CODEX_HOME. Model input includes diagnosis ID/name/description and
the taxonomy: ensure these are diagnosis terminology, not patient notes.
Read-only sandboxing is not a confidentiality boundary against readable files.
For stronger isolation, run the research worker under a separate restricted
account/container with only its input, taxonomy, and Codex credentials available.
Never use sandbox bypass flags for this job.

## Auditing and failure handling

Each run writes a private timestamped directory under
`storage/app/oncotree-mapping/` containing `report.json`, `summary.txt`, per-term
proposal/validated evidence, and private error details when needed. Reports record
the TSV SHA-256, time, instance, configured model, source URLs/quotes, outcomes,
and email delivery status. Codex default model is labeled as default when not pinned.
The script does not store streamed model reasoning. Audit directories are mode 0700
and JSON reports/state are mode 0600. Keep this directory outside public web serving.

A failed diagnosis does not prevent the remaining batch from running. Failures
produce a nonzero exit status and a summary if mail is available. A sendmail failure
does not undo previously committed mappings; use the saved summary to investigate.
A no-new-diagnoses run still sends a completion summary. Dry runs email only `chouh@nih.gov`, with DRY RUN in the subject.
Preflight failures before the audit directory/lock is available are visible in the
cron log; monitor that log as well. Configure log/audit retention for this host.

The job uses the TSV as supplied; it does not update the OncoTree reference table,
refresh materialized views, or install a scheduler. Follow existing data refresh
procedures if your cohorts depend on materialized data. Invalid or missing TSV
files stop processing rather than guessing codes from model memory.


## Oracle and MySQL

The command uses the application's existing `DB_CONNECTION` (`oracle` or `mysql`)
and `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` settings.
There is no separate database switch or credential file for this job. Use
transactional InnoDB tables on MySQL. The required tables/columns are the same:
`cancer_types(ID, NAME, DESCRIPTION)`, `oncotree_mapping(DIAGNOSIS, ID)`, and
`users(email, permissions)`.

Queries use Laravel's database grammar and bound parameters; explicit lowercase
aliases avoid differences in returned column-name casing. Permission JSON is
parsed in PHP, so Oracle/MySQL JSON SQL functions are not required. Diagnosis
comparisons follow the database's existing column collation (MySQL installations
may compare case-insensitively); the original diagnosis string is always stored.

Oracle locks the mapping table within the insert transaction. MySQL uses
`GET_LOCK`/`RELEASE_LOCK` rather than `LOCK TABLES`, avoiding that statement's
transaction side effects. Named locks are connection-scoped and must be released
explicitly: [MySQL locking functions](https://dev.mysql.com/doc/refman/8.4/en/locking-functions.html).
The lock uses the writer connection, never a configured read replica.
SQLite is supported for automated tests, not recommended for this scheduled job.

Validation includes SQLite integration tests and mocked Oracle/MySQL lock paths.
The Oracle read/research path has also had a live dry run; a live MySQL database
was not available for integration testing. Run the documented dry run on each
instance before enabling its cron entry.


## LLM interpretation and evidence

Every eligible unmapped diagnosis goes to the LLM with its ID, name, description,
and the supplied OncoTree taxonomy. There are no direct string-match or alias
insertion shortcuts. The model interprets spelling variants, obvious typos, and
synonyms while preserving the original diagnosis ID for the database.

The proposal records its mapping_basis:
- taxonomy: the model identifies the disease from the supplied taxonomy and
  explains the interpretation. No external citation is required.
- literature: interpretation needs outside evidence. The model researches and
  supplies an authoritative citation with a verifiable quote.
- local_convention: the model applies the user-approved Ewing cohort convention:
  Ewing/Ewing-like defaults to ES; an affirmative soft-tissue/extraosseous diagnosis
  uses ESST. Merely listing "bone or soft tissue" leaves site unspecified and uses
  the ES default. Alternatives, negations, and metastatic sites do not establish
  a soft-tissue primary. CIC/BCOR-altered
  and uncertain labels are excluded from this convention.

The Ewing convention is prompt guidance, not a deterministic string rule.
Taxonomy and local-convention decisions are model judgments, not independently
verified clinical equivalences. Ambiguous cases must remain unmapped. All proposed
codes must exist in the TSV, and existing database mappings are preserved.

Literature quotes tolerate formatting differences but require the same words.
A failed citation gets one research retry with feedback; two failures prevent
insertion. Each attempt is saved for audit. The model must not change evidence
basis simply to evade a failed citation check.

The retry fingerprint includes the interpretation-policy version, so previously
deferred terms are reconsidered after this change. Dry runs email only the
developer and do not modify database mappings.

## Local NORMAL classification

The taxonomy supplied to the LLM includes the additional local code NORMAL when
the TSV does not already define it. The TSV itself is unchanged. The LLM may use
this code for normal/non-tumor tissue or non-tumor-derived cell lines, using
mapping_basis=local_convention. HEK293 belongs to this group by user instruction;
this does not assert that immortalized/transformed cells are biologically normal.
Cancer-derived lines and unknown origins must not be assigned NORMAL merely
because the input says cell line. Interpretation remains with the LLM.
