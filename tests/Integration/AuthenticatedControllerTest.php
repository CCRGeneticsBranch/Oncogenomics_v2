<?php

namespace Tests\Integration;

use LaravelAcl\Authentication\Interfaces\AuthenticateInterface;

class AuthenticatedControllerTest extends InstanceTestCase
{
    private int $testUserId;

    protected function setUp(): void
    {
        parent::setUp();

        $configuredUserId = env('TEST_USER_ID');
        if (! is_numeric($configuredUserId) || (int) $configuredUserId < 1) {
            $this->fail('Set TEST_USER_ID in .env to an active, non-privileged test account.');
        }

        $this->testUserId = (int) $configuredUserId;
        $auth = app(AuthenticateInterface::class);

        $this->assertTrue($auth->loginById($this->testUserId), "Unable to authenticate test user {$this->testUserId}");
        $this->assertSame($this->testUserId, (int) $auth->getLoggedUser()?->id);
    }

    public function test_project_catalog_controller_returns_the_configured_projects(): void
    {
        $response = $this->get('/getProjects');
        $response->assertOk();

        $payload = $this->decodeJson($response->getContent());
        $this->assertNotEmpty($payload['cols']);
        $this->assertNotEmpty($payload['data']);

        foreach ($this->fixtures['projects'] as $project) {
            $this->assertStringContainsString($project['name'], $response->getContent());
        }
    }

    public function test_project_summary_controller_returns_fusions_and_patient_metadata(): void
    {
        $project = $this->fixtures['projects']['primary'];
        $response = $this->get('/getProjectSummary/'.$project['id']);
        $response->assertOk();

        $payload = $this->decodeJson($response->getContent());
        $this->assertIsArray($payload['fusion']);
        $this->assertIsArray($payload['patient_meta']);
    }

    public function test_project_cases_controller_returns_the_configured_case(): void
    {
        $project = $this->fixtures['projects']['primary'];
        $patient = $project['patients'][0];
        $case = $patient['cases'][0];
        $response = $this->get('/getCases/'.$project['id'].'/json/project');
        $response->assertOk();

        $payload = $this->decodeJson($response->getContent());
        $this->assertNotEmpty($payload['cols']);
        $this->assertNotEmpty($payload['data']);
        $this->assertStringContainsString($patient['patient_id'], $response->getContent());
        $this->assertStringContainsString($case['case_id'], $response->getContent());
    }

    public function test_case_page_controller_renders_the_configured_case(): void
    {
        $project = $this->fixtures['projects']['primary'];
        $patient = $project['patients'][0];
        $case = $patient['cases'][0];
        $response = $this->get(sprintf(
            '/viewCase/%s/%s/%s',
            $project['id'],
            rawurlencode($patient['patient_id']),
            rawurlencode($case['case_id']),
        ));

        $response->assertOk();
        $response->assertSee($patient['patient_id']);
    }

    public function test_case_qc_controller_renders_the_configured_case(): void
    {
        $project = $this->fixtures['projects']['primary'];
        $patient = $project['patients'][0];
        $case = $patient['cases'][0];
        $response = $this->get(sprintf(
            '/viewVarQC/%d/%s/%s',
            $project['id'],
            rawurlencode($patient['patient_id']),
            rawurlencode($case['case_id']),
        ));

        $response->assertOk();
        $response->assertSee($patient['patient_id']);
    }

    public function test_cancer_type_catalog_controller_returns_neuroblastoma(): void
    {
        $cancerType = $this->fixtures['cancer_types']['primary'];
        $response = $this->get('/getCancerTypes');
        $response->assertOk();

        $payload = $this->decodeJson($response->getContent());
        $this->assertNotEmpty($payload['cols']);
        $this->assertNotEmpty($payload['data']);
        $this->assertStringContainsString($cancerType['name'], $response->getContent());
    }

    public function test_cancer_type_summary_controller_returns_fusions_and_patient_metadata(): void
    {
        $cancerType = $this->fixtures['cancer_types']['primary'];
        $response = $this->get('/getCancerTypeSummary/'.rawurlencode($cancerType['id']).'/Y');
        $response->assertOk();

        $payload = $this->decodeJson($response->getContent());
        $this->assertIsArray($payload['fusion']);
        $this->assertIsArray($payload['patient_meta']);
    }

    public function test_cancer_type_samples_controller_returns_rows(): void
    {
        $cancerType = $this->fixtures['cancer_types']['primary'];
        $response = $this->get('/getCancerTypeSamples/'.rawurlencode($cancerType['id']).'/json/all/Y');
        $response->assertOk();

        $payload = $this->decodeJson($response->getContent());
        $this->assertNotEmpty($payload['cols']);
        $this->assertNotEmpty($payload['data']);
    }

    private function decodeJson(string $content): array
    {
        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
