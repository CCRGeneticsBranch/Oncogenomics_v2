<?php

namespace Tests\Unit;

use App\Http\Controllers\VarController;
use App\Http\Middleware\AuthorizedToken;
use App\Services\SiteTokenAuthenticator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SiteTokenSecurityTest extends TestCase
{
    public function test_missing_malformed_and_old_tokens_are_rejected_without_disclosure(): void
    {
        config()->set('site.token', 'rotated-site-test-secret');
        foreach ([[], ['token' => 'old-test-secret'], ['token' => ['rotated-site-test-secret']], ['token' => 0]] as $body) {
            $response = (new AuthorizedToken)->handle(Request::create('/getProjects', 'POST', $body), fn () => response('unexpected'));
            $this->assertSame(401, $response->getStatusCode());
            $this->assertSame(['message' => 'Unauthorized.'], $response->getData(true));
        }
        foreach ([null, '', false] as $configured) {
            config()->set('site.token', $configured);
            $this->assertFalse(SiteTokenAuthenticator::valid(''));
            $this->assertFalse(SiteTokenAuthenticator::valid(null));
        }
    }

    public function test_project_list_enforces_token_authentication_for_real_routes(): void
    {
        config()->set('site.token', 'rotated-site-test-secret');
        Schema::create('projects', function (Blueprint $table) {
            $table->integer('id');
            $table->string('name');
        });
        DB::table('projects')->insert(['id' => 1, 'name' => 'Test project']);
        $this->postJson('/getProjects')->assertUnauthorized();
        $this->postJson('/getProjects', ['token' => 'old-test-secret'])->assertUnauthorized();
        $this->postJson('/getProjects', ['token' => 'rotated-site-test-secret'])->assertOk()->assertJsonPath('0.name', 'Test project');
        $this->withToken('rotated-site-test-secret')->postJson('/getProjects')->assertOk();
        $this->withToken('wrong-header')->postJson('/getProjects', ['token' => 'rotated-site-test-secret'])->assertUnauthorized();
    }

    public function test_download_errors_never_echo_credentials_or_touch_data(): void
    {
        config()->set('site.token', 'rotated-site-test-secret');
        $controller = new VarController;
        foreach ([
            $controller->downloadCNV('attacker-test-input', 'P1', 'C1', 'S1', 'sequenza'),
            $controller->downloadVariantsGet('attacker-test-input', 1, 'P1', 'C1', 'somatic'),
        ] as $response) {
            $this->assertSame(403, $response->getStatusCode());
            $this->assertSame('Invalid token.', $response->getContent());
        }
        config()->set('site.token', null);
        $this->assertSame(403, $controller->downloadCNV(null, 'P1', 'C1', 'S1', 'sequenza')->getStatusCode());
    }
}
