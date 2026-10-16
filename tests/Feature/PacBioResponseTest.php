<?php

namespace Tests\Feature;

use Tests\TestCase;

class PacBioResponseTest extends TestCase
{
    public function test_pacbio_json_request_returns_401_for_expired_session(): void
    {
        $this->getJson('/getPacBioData/22606/gene/tp53')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Your session has expired. Please sign in again.']);
    }

    public function test_pacbio_browser_request_still_redirects_to_login(): void
    {
        $this->get('/getPacBioData/22606/gene/tp53')->assertRedirect('/login');
    }
}
