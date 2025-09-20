<?php

namespace Tests\Feature\Server;

use App\Models\ServerService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateServerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_a_php_service_with_selected_version(): void
    {
        $this->actingAs(User::factory()->create());

        $payload = [
            'vanity_name' => 'Production Web Server',
            'public_ip' => '203.0.113.10',
            'private_ip' => '10.0.0.5',
            'php_version' => '8.4',
        ];

        $response = $this->post(route('servers.store'), $payload);

        $response->assertRedirect();

        $this->assertDatabaseHas('servers', [
            'vanity_name' => $payload['vanity_name'],
            'public_ip' => $payload['public_ip'],
        ]);

        $this->assertDatabaseHas('server_services', [
            'service_name' => 'php',
        ]);

        $phpService = ServerService::where('service_name', 'php')->first();

        $this->assertNotNull($phpService);
        $this->assertSame('8.4', $phpService->configuration['version'] ?? null);
    }
}
