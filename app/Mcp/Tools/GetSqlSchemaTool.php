<?php
namespace App\Mcp\Tools;

use App\Models\User;
use App\Services\Sql\SqlCatalog;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class GetSqlSchemaTool extends LegacySchemaTool
{
    protected string $name = 'getSqlSchema';
    protected string $description = 'Discover approved SQL fallback tables and column meanings only when existing tools cannot answer a requested filter, join or aggregation. With table omitted returns table descriptions; supply table and optional column_search to inspect fields. No data values are returned.';
    public function handle(Request $request): ResponseFactory
    {
        if (User::getCurrentUser() === null) return Response::structured(['status'=>'error','action'=>$this->name,'message'=>'Sign in to inspect the SQL schema.']);
        $args = $request->validate(['table'=>'nullable|string|max:100','column_search'=>'nullable|string|max:100']);
        try {
            return Response::structured(['status'=>'success','action'=>$this->name,
                'database'=>\App\Services\Sql\SqlDatabaseContext::describe(),
                'tables'=>app(SqlCatalog::class)->describe($args['table'] ?? null,$args['column_search'] ?? null),
                'sql_contract'=>RunAuthorizedSqlTool::contract(),
                'query_guidance'=>\App\Services\Sql\SqlAgentGuidance::instructions(),
                'notes'=>['Survival times are in days. VAF/MAF are fractions 0–1.',
                    'library_type is capture/library method or ChIPseq target. Discover authorized values using SELECT DISTINCT if needed.',
                    'Mutation type variants means tumor-only; somatic means paired calls. Somatic tier annotation is independent.',
                    'Expression TARGET=_list sample IDs align positionally with gene VALUE_LIST. Do not guess vector matching dimensions.',
                    'Schema descriptions do not grant authorization. Count unit and false-positive exclusions must be explicit.']]);
        } catch (\InvalidArgumentException $e) { return Response::structured(['status'=>'error','action'=>$this->name,'message'=>$e->getMessage()]); }
    }
    protected function legacySchema(): array { return ['type'=>'object','properties'=>[
        'table'=>['type'=>'string','description'=>'Exact approved table name; omit to list tables.'],
        'column_search'=>['type'=>'string','description'=>'Optional substring in column name or description.']], 'additionalProperties'=>false]; }
}
