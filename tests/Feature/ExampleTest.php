<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_the_api_status_endpoint_returns_a_consistent_payload(): void
    {
        $response = $this->getJson('/api/v1/status');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'AMRV API is running')
            ->assertJsonPath('data.version', 'v1')
            ->assertJsonPath('data.status', 'ok');
    }

    public function test_the_unversioned_api_status_endpoint_is_supported(): void
    {
        $response = $this->getJson('/api/status');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok');
    }
}
