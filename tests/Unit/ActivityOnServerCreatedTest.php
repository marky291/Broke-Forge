<?php

namespace Tests\Unit;

use App\Models\Activity;
use App\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityOnServerCreatedTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_created_when_server_created(): void
    {
        $server = Server::factory()->create([
            'name' => 'Production Web',
            'public_ip' => '198.51.100.10',
            'ssh_port' => 22,
        ]);

        $this->assertDatabaseHas('activities', [
            'type' => 'server.created',
            'subject_type' => Server::class,
            'subject_id' => $server->id,
        ]);

        $activity = Activity::where('subject_id', $server->id)->first();
        $this->assertNotNull($activity);
        $this->assertEquals([
            'name' => 'Production Web',
            'public_ip' => '198.51.100.10',
            'ssh_port' => 22,
            'private_ip' => null,
        ], $activity->properties);
    }
}
