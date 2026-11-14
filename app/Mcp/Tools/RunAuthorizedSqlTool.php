<?php
namespace App\Mcp\Tools;

use App\Models\User;
use App\Services\Sql\AuthorizedSelect;
use App\Services\Sql\SqlQueryExecutor;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class RunAuthorizedSqlTool extends LegacySchemaTool
{
    protected string $name = 'runAuthorizedSql';
    protected string $description = 'SQL fallback ONLY for filters, joins or aggregations unsupported by existing tools. An empty result is not a capability gap. Inspect getSqlSchema first for the server-selected database.db_type and SQL contract. Generate one restricted parameterized SELECT with explicit user_projects authorization; every protected table must join its authorized membership. Returns bounded tabular results and actual executed SQL/bindings for technical details.';

    public static function contract(): string
    {
        return \App\Services\Sql\SqlDatabaseContext::instructions()."\n".<<<'TEXT'
            SQL grammar: SELECT [DISTINCT] expressions AS output_alias FROM allowed_table alias
            [INNER JOIN allowed_table alias ON alias.column = alias.column [AND ...]]
            WHERE u.user_id = :auth_user_id [AND qualified_column operator :p1 ...]
            [GROUP BY qualified_columns] [HAVING aggregate operator :p2]
            [ORDER BY output_alias_or_qualified_column [ASC|DESC]].
            Every table needs a unique alias. Every selected expression needs AS and a unique alias.
            Qualified columns and COUNT(*), COUNT(DISTINCT a.column), COUNT/SUM/AVG/MIN/MAX/UPPER/LOWER(a.column), SAFE_NUMBER(a.column), and aggregates over SAFE_NUMBER(a.column) only.
            SAFE_NUMBER converts decimal text to numbers; missing/non-numeric values become NULL. Use it in SELECT and numeric filters/order outputs for metadata attr_value.
            Never compare numeric metadata as text or use LIKE to approximate a numeric threshold. Decimal parameters are converted server-side too.
            WHERE/HAVING supports AND, =, <>, !=, <, >, <=, >=, LIKE, IN (:p1,:p2), IS NULL, IS NOT NULL.
            Bind all values as :p1,:p2,...; pass parameters as [{name:p1,value:...}]. Do not provide auth_user_id; server binds it.
            No quotes/literals, comments, semicolons, *, arithmetic, OR, subqueries, CTEs, UNION, outer/cross joins, DML, DDL or caller LIMIT.
            Exactly one user_projects alias u. Every project_samples/project_patients/project_values/project_processed_cases alias
            must directly join its project_id = u.project_id. projects joins id = u.project_id.
            samples, mutations, var_tier_avia and var_fusion must directly join BOTH patient_id and sample_id to one authorized project_samples alias.
            patients/patient_details join patient_id to authorized project_patients; sample_details joins sample_id to authorized project_samples.
            Additional equality join keys are allowed; authorization keys do not replace analytical join keys.
            Cohort parameters narrow scope, never grant access; fixed page scope overrides model arguments. Diagnosis scope restricts
            each patient/sample membership by diagnosis and rejects expression-vector/case-membership tables.
            Row/byte/time limits apply. Use COUNT(DISTINCT ...) to avoid project membership duplication; counts of rows are not automatically mutation counts.
            Example: SELECT v.left_gene AS gene, COUNT(DISTINCT v.patient_id) AS patients
            FROM var_fusion v INNER JOIN project_samples ps ON v.patient_id = ps.patient_id AND v.sample_id = ps.sample_id
            INNER JOIN user_projects u ON ps.project_id = u.project_id
            WHERE u.user_id = :auth_user_id AND v.left_gene = :p1 GROUP BY v.left_gene ORDER BY patients DESC
        TEXT;
    }

    public function handle(Request $request): ResponseFactory
    {
        $user = User::getCurrentUser();
        if ($user === null) return Response::structured(['status'=>'error','action'=>$this->name,'message'=>'Sign in to query authorized data.']);
        $args = $request->validate([
            'result_role'=>'sometimes|string|in:discovery,answer',
            'sql'=>'required|string|max:16000', 'capability_gap'=>'required|string|min:10|max:1000',
            'parameters'=>'sometimes|array|max:100', 'parameters.*.name'=>'required|string|regex:/^p[1-9][0-9]*$/',
            'parameters.*.value'=>'present|nullable', 'limit'=>'sometimes|integer|min:1|max:500',
            'cohort_type'=>'nullable|string|in:project,cancer_type', 'cohort_id'=>'nullable',
        ]);
        try {
            if (!config('chatbot.sql.enabled', true)) throw new \InvalidArgumentException('SQL fallback is disabled.');
            if (isset($args['cohort_type']) !== isset($args['cohort_id'])) throw new \InvalidArgumentException('Provide both cohort_type and cohort_id, or omit both for all authorized projects.');
            $bindings = [];
            foreach ($args['parameters'] ?? [] as $parameter) {
                if (array_key_exists($parameter['name'],$bindings)) throw new \InvalidArgumentException('Duplicate parameter name.');
                $bindings[$parameter['name']] = $parameter['value'];
            }
            $query = app(AuthorizedSelect::class)->compile($args['sql'],$bindings,(int)$user->id,$args['cohort_type'] ?? null,$args['cohort_id'] ?? null);
            $result = app(SqlQueryExecutor::class)->execute($query,$args['limit'] ?? 100);
            return Response::structured(['status'=>'success','action'=>$this->name,'display_type'=>'table',
                'title'=>($args['result_role'] ?? 'answer') === 'discovery' ? 'SQL metadata discovery' : 'SQL query result',
                'result_role'=>$args['result_role'] ?? 'answer', 'table_json'=>json_encode(['cols'=>$query['columns'],'data'=>$result['rows']],JSON_INVALID_UTF8_SUBSTITUTE),
                'row_count'=>count($result['rows']),'truncated'=>$result['truncated'],'duration_ms'=>$result['duration_ms'],
                'technical_details'=>$result['technical_details'] + ['capability_gap'=>$args['capability_gap']],
                'summary'=>count($result['rows']).' row(s) returned.'.($result['truncated'] ? ' Result or cell values truncated; do not treat as a complete result.' : '')]);
        } catch (\InvalidArgumentException $e) {
            return Response::structured(['status'=>'error','action'=>$this->name,'message'=>$e->getMessage(),'sql_contract'=>self::contract()]);
        } catch (\App\Services\Sql\SqlExecutionException $e) {
            $reference = (string) \Illuminate\Support\Str::uuid();
            Log::warning('Authorized SQL execution failed.', ['error_reference'=>$reference] + $e->diagnostic);
            return Response::structured(['status'=>'error','action'=>$this->name,
                'result_role'=>$args['result_role'] ?? 'answer',
                'message'=>$e->getMessage(), 'error_reference'=>$reference,
                'diagnostic'=>$e->diagnostic, 'technical_details'=>$e->technicalDetails]);
        } catch (\Throwable $e) {
            Log::warning('Authorized SQL execution failed.', ['exception'=>$e::class]);
            return Response::structured(['status'=>'error','action'=>$this->name,'message'=>'SQL could not execute within the configured read-only connection, schema or resource limits. Do not infer that no matching data exists.']);
        }
    }
    protected function legacySchema(): array
    {
        return ['type'=>'object','properties'=>[
            'result_role'=>['type'=>'string','enum'=>['discovery','answer'],'description'=>'Use discovery for exploratory metadata/value queries (technical details only). Use answer for the requested final result.'],
            'sql'=>['type'=>'string','description'=>'Restricted SELECT; inspect getSqlSchema sql_contract first.'],
            'capability_gap'=>['type'=>'string','description'=>'Explain which requested operation existing tools cannot perform.'],
            'parameters'=>['type'=>'array','items'=>['type'=>'object','properties'=>['name'=>['type'=>'string'],'value'=>['type'=>['string','null'],'description'=>'Bound value; encode numeric values as decimal strings. Null is allowed.']],'required'=>['name','value'],'additionalProperties'=>false]],
            'cohort_type'=>['type'=>'string','enum'=>['project','cancer_type']],
            'cohort_id'=>['type'=>'string','description'=>'Resolved project ID or exact diagnosis; omit both cohort fields for all authorized projects.'],
            'limit'=>['type'=>'integer','minimum'=>1,'maximum'=>500]],
            'required'=>['sql','capability_gap'],'additionalProperties'=>false];
    }
}
