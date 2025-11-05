<?php

namespace Tests\Unit\Packages\Services\Nginx;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerNode;
use App\Models\ServerPhp;
use App\Packages\Enums\NodeVersion;
use App\Packages\Enums\PhpVersion;
use App\Packages\Services\Node\NodeInstallerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NginxInstallerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that Node 22 record can be created with correct attributes for provisioning.
     *
     * This test verifies the structure and attributes used by NginxInstaller
     * when creating a Node.js 22 installation during server provisioning.
     */
    public function test_node_22_record_has_correct_provisioning_attributes(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Simulate what NginxInstaller does - create first Node record
        $isFirstNode = $server->nodes()->count() === 0;

        // Act - Create Node 22 record as NginxInstaller does
        $node = ServerNode::create([
            'server_id' => $server->id,
            'version' => NodeVersion::Node22->value,
            'status' => TaskStatus::Pending,
            'is_default' => $isFirstNode,
        ]);

        // Assert - Verify all attributes match provisioning requirements
        $this->assertInstanceOf(ServerNode::class, $node);
        $this->assertEquals($server->id, $node->server_id);
        $this->assertEquals('22', $node->version);
        $this->assertEquals(NodeVersion::Node22->value, $node->version);
        $this->assertEquals(TaskStatus::Pending, $node->status);
        $this->assertTrue($node->is_default, 'First Node should be marked as default');

        // Verify it's persisted correctly
        $this->assertDatabaseHas('server_nodes', [
            'server_id' => $server->id,
            'version' => '22',
            'status' => TaskStatus::Pending->value,
            'is_default' => true,
        ]);
    }

    /**
     * Test that NodeInstallerJob can be instantiated with correct parameters.
     *
     * This test verifies the job structure used by NginxInstaller when
     * dispatching Node installation jobs during provisioning.
     */
    public function test_node_installer_job_accepts_correct_parameters(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $node = ServerNode::create([
            'server_id' => $server->id,
            'version' => NodeVersion::Node22->value,
            'status' => TaskStatus::Pending,
            'is_default' => true,
        ]);

        // Act - Create job as NginxInstaller does
        $job = new NodeInstallerJob($server, $node);

        // Assert - Verify job structure
        $this->assertInstanceOf(NodeInstallerJob::class, $job);
        $this->assertEquals($server->id, $job->server->id);
        $this->assertEquals($node->id, $job->serverNode->id);
        $this->assertEquals('22', $job->serverNode->version);
        $this->assertTrue($job->serverNode->is_default);
    }

    /**
     * Test that Node version uses NodeVersion enum value.
     */
    public function test_node_version_matches_enum_value(): void
    {
        // Arrange & Act
        $version = NodeVersion::Node22->value;

        // Assert
        $this->assertEquals('22', $version);
        $this->assertIsString($version);
    }

    /**
     * Test that first Node installation is marked as default.
     */
    public function test_first_node_installation_logic_sets_default(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Act - Simulate the isFirstNode check from NginxInstaller
        $isFirstNode = $server->nodes()->count() === 0;

        // Assert
        $this->assertTrue($isFirstNode, 'Should detect first node installation');

        // Create the first node
        $node = ServerNode::create([
            'server_id' => $server->id,
            'version' => '22',
            'status' => TaskStatus::Pending,
            'is_default' => $isFirstNode,
        ]);

        $this->assertTrue($node->is_default);
    }

    /**
     * Test that subsequent Node installations are not marked as default.
     */
    public function test_subsequent_node_installation_logic_not_default(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Create first node
        ServerNode::factory()->create([
            'server_id' => $server->id,
            'version' => '20',
            'is_default' => true,
        ]);

        // Act - Simulate the isFirstNode check for second installation
        $isFirstNode = $server->nodes()->count() === 0;

        // Assert
        $this->assertFalse($isFirstNode, 'Should detect this is not the first node');

        // Create second node
        $node = ServerNode::create([
            'server_id' => $server->id,
            'version' => '22',
            'status' => TaskStatus::Pending,
            'is_default' => $isFirstNode,
        ]);

        $this->assertFalse($node->is_default);
    }

    /**
     * Test that Node record starts with pending status.
     */
    public function test_node_record_starts_with_pending_status(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Act - Create node as NginxInstaller does
        $node = ServerNode::create([
            'server_id' => $server->id,
            'version' => NodeVersion::Node22->value,
            'status' => TaskStatus::Pending,
            'is_default' => true,
        ]);

        // Assert
        $this->assertEquals(TaskStatus::Pending, $node->status);
        $this->assertDatabaseHas('server_nodes', [
            'id' => $node->id,
            'status' => TaskStatus::Pending->value,
        ]);
    }

    /**
     * Test that ServerPhp creation is idempotent (safe for retries).
     * Uses firstOrCreate to prevent duplicate key errors on job retries.
     */
    public function test_server_php_creation_is_idempotent(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Act - Create PHP record first time
        $isFirstPhp = $server->phps()->count() === 0;
        $php1 = $server->phps()->firstOrCreate(
            [
                'server_id' => $server->id,
                'version' => PhpVersion::PHP83->value,
            ],
            [
                'status' => TaskStatus::Pending,
                'is_cli_default' => $isFirstPhp,
                'is_site_default' => $isFirstPhp,
            ]
        );

        // Act - Simulate retry (should return existing record, not create duplicate)
        $php2 = $server->phps()->firstOrCreate(
            [
                'server_id' => $server->id,
                'version' => PhpVersion::PHP83->value,
            ],
            [
                'status' => TaskStatus::Pending,
                'is_cli_default' => $isFirstPhp,
                'is_site_default' => $isFirstPhp,
            ]
        );

        // Assert - Same record returned, no duplicate created
        $this->assertEquals($php1->id, $php2->id);
        $this->assertEquals(1, $server->phps()->count());
        $this->assertDatabaseCount('server_phps', 1);
    }

    /**
     * Test that ServerNode creation is idempotent (safe for retries).
     * Uses firstOrCreate to prevent duplicate key errors on job retries.
     */
    public function test_server_node_creation_is_idempotent(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Act - Create Node record first time
        $isFirstNode = $server->nodes()->count() === 0;
        $node1 = $server->nodes()->firstOrCreate(
            [
                'server_id' => $server->id,
                'version' => NodeVersion::Node22->value,
            ],
            [
                'status' => TaskStatus::Pending,
                'is_default' => $isFirstNode,
            ]
        );

        // Act - Simulate retry (should return existing record, not create duplicate)
        $node2 = $server->nodes()->firstOrCreate(
            [
                'server_id' => $server->id,
                'version' => NodeVersion::Node22->value,
            ],
            [
                'status' => TaskStatus::Pending,
                'is_default' => $isFirstNode,
            ]
        );

        // Assert - Same record returned, no duplicate created
        $this->assertEquals($node1->id, $node2->id);
        $this->assertEquals(1, $server->nodes()->count());
        $this->assertDatabaseCount('server_nodes', 1);
    }

    /**
     * Test that firstOrCreate returns existing active record on retry.
     * Verifies that retry doesn't reset status back to pending.
     */
    public function test_first_or_create_preserves_existing_php_record_status(): void
    {
        // Arrange - Create PHP record that's already active
        $server = Server::factory()->create();
        $existingPhp = ServerPhp::create([
            'server_id' => $server->id,
            'version' => PhpVersion::PHP83->value,
            'status' => TaskStatus::Active, // Already installed
            'is_cli_default' => true,
            'is_site_default' => true,
        ]);

        // Act - Simulate retry with firstOrCreate
        $php = $server->phps()->firstOrCreate(
            [
                'server_id' => $server->id,
                'version' => PhpVersion::PHP83->value,
            ],
            [
                'status' => TaskStatus::Pending, // Would set pending if creating new
                'is_cli_default' => true,
                'is_site_default' => true,
            ]
        );

        // Assert - Returns existing record, status stays "active"
        $this->assertEquals($existingPhp->id, $php->id);
        $this->assertEquals(TaskStatus::Active, $php->status);
        $this->assertDatabaseHas('server_phps', [
            'id' => $php->id,
            'status' => TaskStatus::Active->value,
        ]);
    }

    /**
     * Test that firstOrCreate returns existing active Node record on retry.
     */
    public function test_first_or_create_preserves_existing_node_record_status(): void
    {
        // Arrange - Create Node record that's already active
        $server = Server::factory()->create();
        $existingNode = ServerNode::create([
            'server_id' => $server->id,
            'version' => NodeVersion::Node22->value,
            'status' => TaskStatus::Active, // Already installed
            'is_default' => true,
        ]);

        // Act - Simulate retry with firstOrCreate
        $node = $server->nodes()->firstOrCreate(
            [
                'server_id' => $server->id,
                'version' => NodeVersion::Node22->value,
            ],
            [
                'status' => TaskStatus::Pending, // Would set pending if creating new
                'is_default' => true,
            ]
        );

        // Assert - Returns existing record, status stays "active"
        $this->assertEquals($existingNode->id, $node->id);
        $this->assertEquals(TaskStatus::Active, $node->status);
        $this->assertDatabaseHas('server_nodes', [
            'id' => $node->id,
            'status' => TaskStatus::Active->value,
        ]);
    }
}
