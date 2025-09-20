<?php

namespace Tests\Unit;

use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_server_record(): void
    {
        $server = Server::factory()->create([
            'vanity_name' => 'Production Web',
            'public_ip' => '192.0.2.10',
            'private_ip' => '10.0.0.10',
        ]);

        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'public_ip' => '192.0.2.10',
            'private_ip' => '10.0.0.10',
            'ssh_root_user' => 'root',
            'ssh_app_user' => $server->ssh_app_user,
        ]);
    }

    public function test_host_and_port_are_unique(): void
    {
        Server::factory()->create(['public_ip' => '192.0.2.11', 'ssh_port' => 22]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Server::factory()->create(['public_ip' => '192.0.2.11', 'ssh_port' => 22]);
    }
}
