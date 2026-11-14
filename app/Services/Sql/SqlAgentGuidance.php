<?php
namespace App\Services\Sql;

class SqlAgentGuidance
{
    public static function instructions(): string
    {
        return SqlDatabaseContext::instructions()."\n".<<<'TEXT'
            Global SQL scope: when no project/diagnosis was specified, or the user says any/all projects,
            use all authorized projects by omitting both cohort parameters. Do not ask for a cohort
            solely because a domain tool requires one if the SQL fallback can answer the request.
            A named ambiguous cohort still requires resolution; preserve fixed page scope.
            Inspect the relevant table schema once; use confirmed class/type semantics directly.
            Do not fetch distinct attribute names when the documented semantic class already identifies the field.
            For survival time, use patient_details.class=overall_survival and type=number (bound parameters),
            unless another endpoint was requested; state the overall-survival assumption. Units are days.
            attr_value is VARCHAR text even when type=number. SELECT SAFE_NUMBER(pd.attr_value) AS survival_days
            and filter SAFE_NUMBER(pd.attr_value) < :pN for a numeric upper threshold. Never substitute LIKE,
            string ordering, or a prefix search for a numeric comparison. Invalid/missing numbers become NULL.
            For a patient list across projects, SELECT DISTINCT patient_id and the requested values, without
            project name unless requested. Membership joins grant access; they do not require project columns
            in the output. Preserve differing observations for a patient; do not silently choose one or sum them.
            Order numeric outputs numerically. Do not treat returned/truncated rows as total unique patients.
            Plan the final query using the schema, then execute it once; retry only to fix a concrete error
            without changing the requested meaning. Set result_role=discovery for necessary exploratory queries,
            and result_role=answer for the final result. Only the latest successful answer SQL table is primary;
            other SQL executions remain in Technical details. Put multiple requested measures in one result when possible.
            Always finish with a concise direct answer stating scope, units and truncation. Do not reproduce
            discovery tables or a sequence of SQL attempts in the prose; the UI supplies the final result table.
        TEXT;
    }
}
