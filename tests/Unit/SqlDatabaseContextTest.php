<?php
namespace Tests\Unit;

use App\Mcp\Tools\GetSqlSchemaTool;
use App\Mcp\Tools\RunAuthorizedSqlTool;
use App\Services\Sql\SqlAgentGuidance;
use App\Services\Sql\SqlDatabaseContext;
use App\Services\Sql\SqlQueryExecutor;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Mockery;
use PDO;
use PDOStatement;
use Tests\TestCase;

class SqlDatabaseContextTest extends TestCase
{
    public function test_schema_and_prompts_report_the_sql_connection_driver_without_credentials(): void
    {
        config(['chatbot.sql.connection'=>'reporting', 'database.connections.reporting'=>[
            'driver'=>'mysql','host'=>'private-host','database'=>'private-db','username'=>'private-user','password'=>'private-secret','prefix'=>'',
        ]]);
        $auth=Mockery::mock('LaravelAcl\\Authentication\\Interfaces\\AuthenticateInterface');
        $auth->shouldReceive('getLoggedUser')->andReturn((object)['id'=>81]);
        $this->app->instance('LaravelAcl\\Authentication\\Interfaces\\AuthenticateInterface',$auth);
        $schema=(new GetSqlSchemaTool)->handle(new Request(['table'=>'patient_details']))->getStructuredContent();
        $this->assertSame('mysql',$schema['database']['db_type']);
        $this->assertStringContainsString('Target MySQL',SqlAgentGuidance::instructions());
        $this->assertStringContainsString('"db_type":"mysql"',RunAuthorizedSqlTool::contract());
        $json=json_encode($schema);
        foreach (['private-host','private-db','private-user','private-secret','reporting'] as $secret) $this->assertStringNotContainsString($secret,$json);
        $this->assertStringContainsString('patient_details',$json);
    }

    public function test_default_connection_and_url_resolution_match_execution_configuration(): void
    {
        config(['chatbot.sql.connection'=>null]);
        $this->assertSame('sqlite',SqlDatabaseContext::describe()['db_type']);
        config(['chatbot.sql.connection'=>'url_fixture','database.connections.url_fixture'=>[
            'driver'=>'oracle', 'url'=>'mysql://fixture_user:fixture_password@localhost/fixture_db','prefix'=>'',
        ]]);
        $this->assertSame('mysql',SqlDatabaseContext::describe()['db_type']);
        $this->assertSame('mysql',SqlDatabaseContext::configuration()['driver']);
        $this->assertStringNotContainsString('fixture_password',SqlDatabaseContext::instructions());
    }

    public function test_mysql_executor_uses_native_numeric_sql_limits_and_read_only_transaction(): void
    {
        $pdo=Mockery::mock(PDO::class);
        $statement=Mockery::mock(PDOStatement::class);
        $connection=Mockery::mock();
        $connection->shouldReceive('getDriverName')->once()->andReturn('mysql');
        $connection->shouldReceive('getPdo')->once()->andReturn($pdo);
        $source=Mockery::mock();
        $source->shouldReceive('getConfig')->once()->andReturn(['driver'=>'mysql','prefix'=>'']);
        config(['chatbot.sql.connection'=>'sql_mysql_test', 'chatbot.sql.timeout_ms'=>5000]);
        DB::shouldReceive('connection')->with('sql_mysql_test')->once()->andReturn($source);
        DB::shouldReceive('purge')->with('chatbot_sql_runtime')->twice();
        DB::shouldReceive('connection')->with('chatbot_sql_runtime')->once()->andReturn($connection);
        $pdo->shouldReceive('exec')->with('SET SESSION MAX_EXECUTION_TIME = 5000')->once()->ordered()->andReturn(0);
        $pdo->shouldReceive('exec')->with('SET TRANSACTION READ ONLY')->once()->ordered()->andReturn(0);
        $pdo->shouldReceive('beginTransaction')->once()->ordered()->andReturn(true);
        $pdo->shouldReceive('prepare')->once()->ordered()->with(Mockery::on(function ($sql) {
            $this->assertStringContainsString('CAST(TRIM(pd.attr_value) AS DECIMAL(30,12))',$sql);
            $this->assertStringContainsString('MAX_EXECUTION_TIME(5000)',$sql);
            $this->assertStringContainsString('LIMIT 11',$sql);
            $this->assertStringContainsString('u.user_id = ?',$sql);
            $this->assertStringNotContainsString('ROWNUM',$sql);
            $this->assertStringNotContainsString('TO_NUMBER',$sql);
            $this->assertStringNotContainsString('SAFE_NUMBER',$sql);
            return true;
        }))->andReturn($statement);
        $statement->shouldReceive('bindValue')->with(1,81,PDO::PARAM_INT)->once()->andReturn(true);
        $statement->shouldReceive('bindValue')->with(2,'200',PDO::PARAM_STR)->once()->andReturn(true);
        $statement->shouldReceive('bindValue')->with(3,'200',PDO::PARAM_STR)->once()->andReturn(true);
        $statement->shouldReceive('execute')->once()->andReturn(true);
        $statement->shouldReceive('fetch')->with(PDO::FETCH_ASSOC)->twice()->andReturn(['survival_days'=>'199.000000000000'],false);
        $statement->shouldReceive('closeCursor')->once()->andReturn(true);
        $pdo->shouldReceive('inTransaction')->once()->andReturn(true);
        $pdo->shouldReceive('rollBack')->once()->andReturn(true);
        $result=app(SqlQueryExecutor::class)->execute([
            'sql'=>'SELECT SAFE_NUMBER(pd.attr_value) AS survival_days FROM patient_details pd INNER JOIN project_patients pp ON pd.patient_id = pp.patient_id INNER JOIN user_projects u ON pp.project_id = u.project_id WHERE u.user_id = :auth_user_id AND SAFE_NUMBER(pd.attr_value) < SAFE_NUMBER(:p1)',
            'bindings'=>['auth_user_id'=>81,'p1'=>'200'],'columns'=>['survival_days'],
        ],10);
        $this->assertSame('mysql',$result['technical_details']['dialect']);
        $this->assertSame([81,'200','200'],$result['technical_details']['bindings']);
        $this->assertTrue($result['technical_details']['read_only']);
        $this->assertSame([['199.000000000000']],$result['rows']);
    }
    public function test_oracle_error_diagnostics_exclude_raw_database_messages(): void
    {
        foreach (['ORA-01013'=>'timeout_or_cancelled','ORA-00904'=>'schema_mismatch','ORA-00932'=>'datatype_mismatch','ORA-00933'=>'sql_syntax'] as $code=>$category) {
            $error=new \App\Services\Sql\SqlExecutionException(new \RuntimeException($code.': sensitive-host SQL and secret bindings'), ['sql'=>'SELECT approved_column'], 'execute');
            $this->assertSame($category,$error->diagnostic['category']);
            $this->assertSame($code,$error->diagnostic['database_code']);
            $this->assertStringNotContainsString('sensitive-host',json_encode($error->diagnostic).$error->getMessage());
            $this->assertStringNotContainsString('secret bindings',$error->getMessage());
        }
    }

    public function test_failed_sql_tool_returns_safe_error_code_and_attempted_statement(): void
    {
        $auth=Mockery::mock('LaravelAcl\\Authentication\\Interfaces\\AuthenticateInterface');
        $auth->shouldReceive('getLoggedUser')->andReturn((object)['id'=>81]);
        $this->app->instance('LaravelAcl\\Authentication\\Interfaces\\AuthenticateInterface',$auth);
        $executor=Mockery::mock(SqlQueryExecutor::class);
        $details=['sql'=>'SELECT u.project_id AS project_id FROM user_projects u WHERE u.user_id = ?', 'bindings'=>[81], 'execution_status'=>'failed', 'dialect'=>'oracle'];
        $executor->shouldReceive('execute')->once()->andThrow(new \App\Services\Sql\SqlExecutionException(new \RuntimeException('ORA-01013: private-driver-details'),$details,'execute'));
        $this->app->instance(SqlQueryExecutor::class,$executor);
        $result=(new RunAuthorizedSqlTool)->handle(new Request([
            'sql'=>'SELECT u.project_id AS project_id FROM user_projects u WHERE u.user_id = :auth_user_id',
            'capability_gap'=>'Custom unsupported data aggregation',
        ]))->getStructuredContent();
        $this->assertSame('error',$result['status']);
        $this->assertSame('ORA-01013',$result['diagnostic']['database_code']);
        $this->assertSame($details,$result['technical_details']);
        $this->assertNotEmpty($result['error_reference']);
        $this->assertStringNotContainsString('private-driver-details',json_encode($result));
    }

    public function test_sql_arguments_are_not_cut_off_at_500_characters(): void
    {
        $sql='SELECT '.str_repeat('column_name, ',60).'final_column FROM approved_table WHERE user_id = :auth_user_id';
        $method=new \ReflectionMethod(\App\Services\ClinomicsChatAgentRunner::class,'presentArguments');
        $result=$method->invoke(app(\App\Services\ClinomicsChatAgentRunner::class),['sql'=>$sql,'password'=>'secret','other'=>str_repeat('x',900)]);
        $this->assertSame($sql,$result['sql']);
        $this->assertSame('[redacted]',$result['password']);
        $this->assertSame(500,strlen($result['other']));
    }

}
