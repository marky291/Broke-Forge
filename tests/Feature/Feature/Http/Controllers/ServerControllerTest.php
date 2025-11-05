<?php

namespace Tests\Feature\Feature\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that server creation stores the selected PHP version in provision_config.
     */
    public function test_server_creation_stores_php_version_in_provision_config(): void
    {
        // Arrange
        $user = User::factory()->create();
        $data = [
            'vanity_name' => 'Test Server',
            'public_ip' => '192.168.1.100',
            'ssh_port' => 22,
            'php_version' => '8.3',
        ];

        // Act
        $response = $this->actingAs($user)->post(route('servers.store'), $data);

        // Assert
        $response->assertRedirect();
        $this->assertDatabaseHas('servers', [
            'vanity_name' => 'Test Server',
            'public_ip' => '192.168.1.100',
        ]);

        $server = \App\Models\Server::where('public_ip', '192.168.1.100')->first();
        $this->assertNotNull($server->provision_config);
        $this->assertEquals('8.3', $server->provision_config->get('php_version'));
    }

    /**
     * Test that different PHP versions are stored correctly in provision_config.
     */
    public function test_different_php_versions_are_stored_correctly(): void
    {
        // Arrange
        $phpVersions = ['8.1', '8.2', '8.3', '8.4'];
        $counter = 10;

        foreach ($phpVersions as $version) {
            // Create a new user for each server to avoid server limit issues
            $user = User::factory()->create();
            $ip = "192.168.1.{$counter}";
            $data = [
                'vanity_name' => "Test Server {$version}",
                'public_ip' => $ip,
                'ssh_port' => 22,
                'php_version' => $version,
            ];

            // Act
            $response = $this->actingAs($user)->post(route('servers.store'), $data);

            // Assert
            $response->assertRedirect();
            $server = \App\Models\Server::where('public_ip', $ip)->first();
            $this->assertNotNull($server, "Server with IP {$ip} was not created");
            $this->assertEquals($version, $server->provision_config->get('php_version'));

            $counter++;
        }
    }

    /**
     * Test that provision_config is a collection after retrieval.
     */
    public function test_provision_config_is_cast_as_collection(): void
    {
        // Arrange
        $user = User::factory()->create();
        $data = [
            'vanity_name' => 'Test Server',
            'public_ip' => '192.168.1.200',
            'ssh_port' => 22,
            'php_version' => '8.4',
        ];

        // Act
        $this->actingAs($user)->post(route('servers.store'), $data);

        // Assert
        $server = \App\Models\Server::where('public_ip', '192.168.1.200')->first();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $server->provision_config);
        $this->assertTrue($server->provision_config->has('php_version'));
    }
}
