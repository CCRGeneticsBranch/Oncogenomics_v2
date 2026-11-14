<?php
namespace Tests\Unit;

use App\Services\Sql\AuthorizedSelect;
use App\Services\Sql\SqlQueryExecutor;
use App\Ai\Support\ChatbotRunContext;
use App\Ai\Support\ToolResultCompactor;
use App\Ai\Tools\ScopedMcpTool;
use App\Mcp\Tools\RunAuthorizedSqlTool;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class AuthorizedSqlTest extends TestCase
{
    private function sql(): string
    {
        return 'SELECT v.left_gene AS gene, COUNT(DISTINCT v.patient_id) AS patients FROM var_fusion v '
            .'INNER JOIN project_samples ps ON v.patient_id = ps.patient_id AND v.sample_id = ps.sample_id '
            .'INNER JOIN user_projects u ON ps.project_id = u.project_id '
            .'WHERE u.user_id = :auth_user_id AND v.left_gene = :p1 GROUP BY v.left_gene ORDER BY patients DESC';
    }
    public function test_compiles_only_authorized_query_and_enforces_fixed_scope(): void
    {
        $q=app(AuthorizedSelect::class)->compile($this->sql(),['p1'=>'PAX3'],81,'project',42);
        $this->assertSame(81,$q['bindings']['auth_user_id']);
        $this->assertSame(42,$q['bindings']['scope_project_id']);
        $this->assertStringContainsString('AND u.project_id = :scope_project_id GROUP BY',$q['sql']);
        $this->assertSame(['gene','patients'],$q['columns']);
        $q=app(AuthorizedSelect::class)->compile($this->sql(),['p1'=>'PAX3'],81,'cancer_type','RMS');
        $this->assertStringContainsString('ps.diagnosis = :scope_diagnosis',$q['sql']);
    }
    public function test_rejects_bypasses_and_non_read_only_sql(): void
    {
        $queries=[
            'DELETE FROM user_projects',
            $this->sql().'; DELETE FROM user_projects',
            $this->sql().' UNION SELECT x.left_gene AS gene FROM var_fusion x',
            str_replace('INNER JOIN project_samples','LEFT JOIN project_samples',$this->sql()),
            str_replace('u.user_id = :auth_user_id','u.user_id = :p2',$this->sql()),
            str_replace('u.user_id = :auth_user_id','u.user_id = :auth_user_id OR u.user_id = :p1',$this->sql()),
            str_replace(' AND v.sample_id = ps.sample_id','',$this->sql()),
            str_replace('ps.project_id = u.project_id','ps.project_id = ps.project_id',$this->sql()),
            str_replace('COUNT(DISTINCT v.patient_id)','evil_function(v.patient_id)',$this->sql()),
            str_replace('var_fusion v','secret_table v',$this->sql()),
            str_replace('FROM var_fusion v','FROM (SELECT * FROM var_fusion) v',$this->sql()),
            str_replace('v.left_gene AS gene','v.left_gene AS gene INTO outfile',$this->sql()),
            $this->sql().' FOR UPDATE',
            'WITH x AS ('.$this->sql().') SELECT * FROM x',
            str_replace(':p1',"'PAX3'",$this->sql()),
            str_replace('SELECT','SELECT /* bypass */',$this->sql()),
            str_replace('v.left_gene AS gene','v.not_a_column AS gene',$this->sql()),
            str_replace('WHERE','JOIN var_fusion rogue ON rogue.patient_id = v.patient_id WHERE',$this->sql()),
            str_replace('WHERE','JOIN project_samples rogue ON rogue.patient_id = v.patient_id AND rogue.sample_id = v.sample_id WHERE',$this->sql()),
        ];
        foreach($queries as $sql) {
            try {app(AuthorizedSelect::class)->compile($sql,['p1'=>'PAX3'],81);$this->fail('Accepted unsafe query: '.$sql);}
            catch(InvalidArgumentException $e){$this->assertNotEmpty($e->getMessage());}
        }
    }
    public function test_parameters_cannot_override_identity_or_inject_sql(): void
    {
        $value="PAX3' OR true --";
        $q=app(AuthorizedSelect::class)->compile($this->sql(),['p1'=>$value],81);
        $this->assertStringNotContainsString($value,$q['sql']);
        $this->assertSame($value,$q['bindings']['p1']);
        foreach([['p1'=>'PAX3','auth_user_id'=>99],[],['p1'=>['bad']]] as $bindings) {
            try {app(AuthorizedSelect::class)->compile($this->sql(),$bindings,81);$this->fail('Invalid bindings accepted');}
            catch(InvalidArgumentException){$this->addToAssertionCount(1);}
        }
    }
    public function test_scope_adapter_overrides_model_selected_cohort(): void
    {
        $adapter=new ScopedMcpTool(new RunAuthorizedSqlTool,new ChatbotRunContext('project',42,'Study','Unsupported custom query'),new ToolResultCompactor);
        $args=$adapter->applyScope(['cohort_type'=>'project','cohort_id'=>999,'sql'=>$this->sql()]);
        $this->assertSame('project',$args['cohort_type']);
        $this->assertSame(42,$args['cohort_id']);
    }
    public function test_model_cannot_execute_without_authenticated_identity(): void
    {
        $auth=\Mockery::mock('LaravelAcl\\Authentication\\Interfaces\\AuthenticateInterface');
        $auth->shouldReceive('getLoggedUser')->andReturn(null);
        $this->app->instance('LaravelAcl\\Authentication\\Interfaces\\AuthenticateInterface',$auth);
        $executor=\Mockery::mock(SqlQueryExecutor::class);
        $executor->shouldNotReceive('execute');
        $this->app->instance(SqlQueryExecutor::class,$executor);
        $result=(new RunAuthorizedSqlTool)->handle(new \Laravel\Mcp\Request(['sql'=>$this->sql(),'capability_gap'=>'Unsupported aggregation']))->getStructuredContent();
        $this->assertSame('error',$result['status']);
        $this->assertStringContainsString('Sign in',$result['message']);
    }

    public function test_case_and_expression_access_still_requires_user_projects_and_diagnosis_restrictions(): void
    {
        $sql='SELECT c.case_id AS case_id FROM project_processed_cases c INNER JOIN user_projects u ON c.project_id = u.project_id WHERE u.user_id = :auth_user_id';
        $this->assertStringContainsString('c.project_id = u.project_id',app(AuthorizedSelect::class)->compile($sql,[],81)['sql']);
        $bad=str_replace('c.project_id = u.project_id','c.project_id = u.user_id',$sql);
        try {app(AuthorizedSelect::class)->compile($bad,[],81);$this->fail('Case authorization bypass');}
        catch(InvalidArgumentException){$this->addToAssertionCount(1);}
        $sql='SELECT pv.symbol AS symbol FROM project_values pv INNER JOIN user_projects u ON pv.project_id = u.project_id WHERE u.user_id = :auth_user_id';
        $this->assertSame(81,app(AuthorizedSelect::class)->compile($sql,[],81)['bindings']['auth_user_id']);
        try {app(AuthorizedSelect::class)->compile($sql,[],81,'cancer_type','RMS');$this->fail('Expression vectors cannot be diagnosis restricted this way');}
        catch(InvalidArgumentException){$this->addToAssertionCount(1);}
    }

    public function test_actual_sql_reaches_technical_details_without_truncation_or_interpolation(): void
    {
        $technical=['sql'=>'SELECT ps.sample_id AS sample FROM project_samples ps WHERE ps.project_id = ?', 'bindings'=>[42],'read_only'=>true];
        $method=new \ReflectionMethod(\App\Services\ClinomicsChatAgentRunner::class,'presentExecutions');
        $out=$method->invoke(app(\App\Services\ClinomicsChatAgentRunner::class),[
            ['tool'=>'runAuthorizedSql','arguments'=>[],'result'=>['status'=>'success','action'=>'runAuthorizedSql',
                'technical_details'=>$technical,'table_json'=>json_encode(['cols'=>['sample'],'data'=>[['S1']]])]],
        ]);
        $this->assertSame($technical,$out[0]['technical_details']);
        $this->assertSame([['S1']],$out[0]['table']['rows']);
    }

    public function test_queries_exclude_other_users_samples_even_when_patient_is_shared(): void
    {
        $path=tempnam(sys_get_temp_dir(),'authorized-sql-');
        config(['database.connections.sql_fixture'=>['driver'=>'sqlite','database'=>$path,'prefix'=>''], 'chatbot.sql.connection'=>'sql_fixture']);
        try {
            $db=DB::connection('sql_fixture');
            $db->statement('CREATE TABLE user_projects (user_id INTEGER, project_id INTEGER)');
            $db->statement('CREATE TABLE project_samples (project_id INTEGER, patient_id TEXT, sample_id TEXT, diagnosis TEXT)');
            $db->statement('CREATE TABLE var_fusion (patient_id TEXT, sample_id TEXT, left_gene TEXT)');
            $db->table('user_projects')->insert([['user_id'=>81,'project_id'=>42],['user_id'=>99,'project_id'=>43]]);
            $db->table('project_samples')->insert([['project_id'=>42,'patient_id'=>'P1','sample_id'=>'S1','diagnosis'=>'RMS'],['project_id'=>43,'patient_id'=>'P1','sample_id'=>'S2','diagnosis'=>'RMS'],['project_id'=>43,'patient_id'=>'P2','sample_id'=>'S3','diagnosis'=>'RMS']]);
            $db->table('var_fusion')->insert([['patient_id'=>'P1','sample_id'=>'S1','left_gene'=>'PAX3'],['patient_id'=>'P1','sample_id'=>'S2','left_gene'=>'PAX3'],['patient_id'=>'P2','sample_id'=>'S3','left_gene'=>'PAX3']]);
            foreach([81=>1,99=>2,88=>0] as $user=>$expected) {
                $q=app(AuthorizedSelect::class)->compile($this->sql(),['p1'=>'PAX3'],$user);
                $r=app(SqlQueryExecutor::class)->execute($q,100);
                $this->assertSame($expected,(int)($r['rows'][0][1] ?? 0));
                $this->assertTrue($r['technical_details']['read_only']);
                $this->assertStringContainsString('u.user_id = ?',$r['technical_details']['sql']);
                $this->assertSame($user,$r['technical_details']['bindings'][0]);
            }
            $q=app(AuthorizedSelect::class)->compile($this->sql(),['p1'=>'PAX3'],81,'project',43);
            $this->assertSame([],app(SqlQueryExecutor::class)->execute($q,100)['rows']);
            $this->assertSame(3,$db->table('var_fusion')->count());
        } finally {DB::purge('sql_fixture');unlink($path);}
    }
    private function survivalSql(): string
    {
        return 'SELECT DISTINCT pd.patient_id AS patient_id, SAFE_NUMBER(pd.attr_value) AS survival_days '
            .'FROM patient_details pd INNER JOIN project_patients pp ON pd.patient_id = pp.patient_id '
            .'INNER JOIN user_projects u ON pp.project_id = u.project_id '
            .'WHERE u.user_id = :auth_user_id AND pd.class = :p1 AND pd.type = :p2 '
            .'AND SAFE_NUMBER(pd.attr_value) < :p3 ORDER BY survival_days ASC';
    }

    public function test_survival_filter_is_numeric_deduplicated_and_authorized(): void
    {
        $path=tempnam(sys_get_temp_dir(),'survival-sql-');
        config(['database.connections.sql_fixture'=>['driver'=>'sqlite','database'=>$path,'prefix'=>''], 'chatbot.sql.connection'=>'sql_fixture']);
        try {
            $db=DB::connection('sql_fixture');
            $db->statement('CREATE TABLE user_projects (user_id INTEGER, project_id INTEGER)');
            $db->statement('CREATE TABLE project_patients (project_id INTEGER, patient_id TEXT)');
            $db->statement('CREATE TABLE patient_details (patient_id TEXT, attr_value TEXT, class TEXT, type TEXT)');
            $db->table('user_projects')->insert([['user_id'=>81,'project_id'=>42],['user_id'=>81,'project_id'=>43],['user_id'=>99,'project_id'=>44]]);
            foreach (['1580','1771','199','200','20','2','1000','0','10.5','NA','',null,'1e999'] as $i=>$value) {
                $db->table('patient_details')->insert(['patient_id'=>'P'.$i,'attr_value'=>$value,'class'=>'overall_survival','type'=>'number']);
                $db->table('project_patients')->insert([['project_id'=>42,'patient_id'=>'P'.$i],['project_id'=>43,'patient_id'=>'P'.$i]]);
            }
            $db->table('patient_details')->insert(['patient_id'=>'PRIVATE','attr_value'=>'1','class'=>'overall_survival','type'=>'number']);
            $db->table('project_patients')->insert(['project_id'=>44,'patient_id'=>'PRIVATE']);
            $query=app(AuthorizedSelect::class)->compile($this->survivalSql(),['p1'=>'overall_survival','p2'=>'number','p3'=>'200'],81);
            $result=app(SqlQueryExecutor::class)->execute($query,100);
            $this->assertEquals([0,2,10.5,20,199],array_column($result['rows'],1));
            $this->assertCount(5,$result['rows']);
            $this->assertNotContains('PRIVATE',array_column($result['rows'],0));
            $this->assertFalse($result['truncated']);
            $this->assertStringNotContainsString('SAFE_NUMBER(', $result['technical_details']['sql']);
            $this->assertStringContainsString('clinomics_safe_number(?)', $result['technical_details']['sql']);
        } finally {DB::purge('sql_fixture');unlink($path);}
    }

    public function test_rejects_lexical_numeric_metadata_filters_and_like_substitution(): void
    {
        foreach (['pd.attr_value < :p3','pd.attr_value LIKE :p3','SAFE_NUMBER(pd.attr_value) LIKE :p3'] as $filter) {
            $sql=str_replace('SAFE_NUMBER(pd.attr_value) < :p3',$filter,$this->survivalSql());
            try {app(AuthorizedSelect::class)->compile($sql,['p1'=>'overall_survival','p2'=>'number','p3'=>'200'],81);$this->fail('Accepted text numeric filter');}
            catch(InvalidArgumentException $e){$this->assertStringContainsString('numeric',strtolower($e->getMessage()));}
        }
        $this->expectException(InvalidArgumentException::class);
        app(AuthorizedSelect::class)->compile($this->survivalSql(),['p1'=>'overall_survival','p2'=>'number','p3'=>'2%'],81);
    }

    public function test_safe_number_does_not_allow_functions_or_authorization_bypasses(): void
    {
        foreach ([str_replace('SAFE_NUMBER(pd.attr_value)', 'SAFE_NUMBER(evil(pd.attr_value))', $this->survivalSql()),
            str_replace('pp.project_id = u.project_id', 'pp.project_id = u.user_id', $this->survivalSql())] as $sql) {
            try {app(AuthorizedSelect::class)->compile($sql,['p1'=>'overall_survival','p2'=>'number','p3'=>'200'],81);$this->fail('Accepted unsafe numeric SQL');}
            catch(InvalidArgumentException){$this->addToAssertionCount(1);}
        }
        foreach (['oracle','mysql'] as $driver) {
            $rendered=\App\Services\Sql\NumericSqlExpression::render('SAFE_NUMBER(pd.attr_value) < SAFE_NUMBER(:p3)',$driver);
            $this->assertStringContainsString('CASE WHEN',$rendered);
            $this->assertStringContainsString('ELSE NULL END',$rendered);
            $this->assertStringNotContainsString('SAFE_NUMBER',$rendered);
        }
    }

    public function test_only_final_answer_sql_is_primary_and_truncation_is_preserved(): void
    {
        $executions=[];
        foreach (['discovery','answer','answer'] as $role) $executions[]=['tool'=>'runAuthorizedSql','arguments'=>[],
            'result'=>['status'=>'success','result_role'=>$role,'truncated'=>true,
                'table_json'=>json_encode(['cols'=>['patient_id'],'data'=>[['P1']]])]];
        $executions[]=['tool'=>'getSqlSchema','arguments'=>[],'result'=>['status'=>'success']];
        $method=new \ReflectionMethod(\App\Services\ClinomicsChatAgentRunner::class,'presentExecutions');
        $out=$method->invoke(app(\App\Services\ClinomicsChatAgentRunner::class),$executions);
        $this->assertSame([false,false,true,false],array_column($out,'show_primary'));
        $this->assertCount(4,$out); // Full technical history is retained.
        $failed=$executions;
        $failed[]=['tool'=>'runAuthorizedSql','arguments'=>[],'result'=>['status'=>'error','result_role'=>'answer']];
        $failedOut=$method->invoke(app(\App\Services\ClinomicsChatAgentRunner::class),$failed);
        $this->assertSame([false,false,false,false,true],array_column($failedOut,'show_primary'));
        $this->assertSame('error',$failedOut[4]['status']);

        $this->assertTrue($out[2]['table']['truncated']);
        $compact=(new ToolResultCompactor)->compact($executions[2]['result']);
        $this->assertTrue($compact['table_preview']['truncated']);
    }

    public function test_empty_answer_gets_tool_free_final_synthesis_and_failure_is_explicit(): void
    {
        \App\Ai\Agents\ClinomicsResultSummarizer::fake(['Found five matching patient observations under 200 days.']);
        $method=new \ReflectionMethod(\App\Services\ClinomicsChatAgentRunner::class,'ensureAnswer');
        $runner=app(\App\Services\ClinomicsChatAgentRunner::class);
        $executions=[['tool'=>'runAuthorizedSql','arguments'=>[],'result'=>['status'=>'success','result_role'=>'answer',
            'summary'=>'5 rows returned','table_json'=>json_encode(['cols'=>['patient_id'],'data'=>[['P1']]])]]];
        $answer=$method->invoke($runner,'','any projects',[['role'=>'user','content'=>'Patients with survival less than 200 days']],$executions,['gemini'=>'test-model'],10);
        $this->assertStringContainsString('under 200 days',$answer);
        $answer=$method->invoke($runner,'','any projects',[],[],['gemini'=>'test-model'],10);
        $this->assertStringContainsString('did not produce a final answer',$answer);
        $this->assertStringNotContainsString('No answer was returned',$answer);
        $this->assertSame('Existing answer',$method->invoke($runner,'Existing answer','query',[],[],[],10));
    }

}
