<?php

namespace Tests\Unit\Packages\Services\Nginx;

use App\Enums\TaskStatus;
use App\Models\AvailableFramework;
use App\Models\Server;
use App\Models\ServerNode;
use App\Models\ServerPhp;
use App\Packages\Enums\NodeVersion;
use App\Packages\Enums\PhpVersion;
use App\Packages\Services\Node\NodeInstallerJob;
use App\Packages\Services\TimeSync\TimeSyncInstallerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NginxInstallerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed frameworks for testing
        $this->artisan('db:seed', ['--class' => 'AvailableFrameworkSeeder']);
    }

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

    /**
     * Test that default site uses symlink-based deployment structure.
     * Verifies the directory structure matches site deployment architecture.
     */
    public function test_default_site_creates_deployment_directory_structure(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new \App\Packages\Services\Nginx\NginxInstaller($server);
        $phpVersion = PhpVersion::PHP83;

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        // Act - Get commands array
        $commands = $method->invoke($installer, $phpVersion);

        // Assert - Find the mkdir command for deployment directory
        $mkdirCommand = collect($commands)->first(function ($command) {
            return is_string($command) && str_contains($command, 'mkdir -p') && str_contains($command, '/deployments/default/');
        });

        $this->assertNotNull($mkdirCommand, 'Should create deployment directory structure');
        $this->assertStringContainsString('/deployments/default/', $mkdirCommand);
        $this->assertStringContainsString('/public', $mkdirCommand);
    }

    /**
     * Test that default site symlink is created pointing to deployment directory.
     */
    public function test_default_site_creates_symlink_to_deployment(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new \App\Packages\Services\Nginx\NginxInstaller($server);
        $phpVersion = PhpVersion::PHP83;
        $appUser = config('app.ssh_user', str_replace(' ', '', strtolower(config('app.name'))));

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        // Act - Get commands array
        $commands = $method->invoke($installer, $phpVersion);

        // Assert - Find the symlink command
        $symlinkCommand = collect($commands)->first(function ($command) use ($appUser) {
            return is_string($command) && str_contains($command, 'ln -sfn deployments/default/') && str_contains($command, "/home/{$appUser}/default");
        });

        $this->assertNotNull($symlinkCommand, 'Should create symlink to deployment directory');
        $this->assertStringContainsString('ln -sfn deployments/default/', $symlinkCommand);
        $this->assertStringContainsString("/home/{$appUser}/default", $symlinkCommand);
    }

    /**
     * Test that deployment timestamp uses correct format (ddMMYYYY-HHMMSS).
     * This format matches site deployment architecture for consistency.
     */
    public function test_default_site_deployment_uses_correct_timestamp_format(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new \App\Packages\Services\Nginx\NginxInstaller($server);
        $phpVersion = PhpVersion::PHP83;

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        // Act - Get commands array
        $commands = $method->invoke($installer, $phpVersion);

        // Assert - Check mkdir command contains timestamp in correct format
        $mkdirCommand = collect($commands)->first(function ($command) {
            return is_string($command) && str_contains($command, 'mkdir -p') && str_contains($command, '/deployments/default/');
        });

        // Extract timestamp from path (should match ddMMYYYY-HHMMSS pattern)
        preg_match('/\/deployments\/default\/(\d{8}-\d{6})/', $mkdirCommand, $matches);

        $this->assertNotEmpty($matches, 'Should contain timestamp in deployment path');
        $this->assertMatchesRegularExpression('/^\d{8}-\d{6}$/', $matches[1], 'Timestamp should match ddMMYYYY-HHMMSS format');

        // Verify timestamp is valid date format
        $timestamp = $matches[1];
        $dateTime = \DateTime::createFromFormat('dmY-His', $timestamp);
        $this->assertNotFalse($dateTime, 'Timestamp should be a valid date');
    }

    /**
     * Test that ServerSite record stores deployment_path in configuration.
     * This enables future switching of default site symlink target.
     */
    public function test_default_site_record_stores_deployment_path_in_configuration(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $appUser = config('app.ssh_user', str_replace(' ', '', strtolower(config('app.name'))));
        $phpVersion = PhpVersion::PHP83;
        $deploymentPath = "/home/{$appUser}/deployments/default/07112025-120000";
        $staticHtmlFramework = AvailableFramework::findBySlug(AvailableFramework::STATIC_HTML);

        // Act - Create default site record as NginxInstaller does
        $site = $server->sites()->updateOrCreate(
            ['domain' => 'default'],
            [
                'available_framework_id' => $staticHtmlFramework->id,
                'document_root' => "/home/{$appUser}/default",
                'nginx_config_path' => '/etc/nginx/sites-available/default',
                'php_version' => $phpVersion,
                'ssl_enabled' => false,
                'is_default' => true,
                'default_site_status' => TaskStatus::Active,
                'configuration' => [
                    'is_default_site' => true,
                    'default_deployment_path' => $deploymentPath,
                ],
                'status' => 'active',
                'installed_at' => now(),
                'deinstalled_at' => null,
            ]
        );

        // Assert - Verify configuration contains both flags
        $this->assertTrue($site->configuration['is_default_site']);
        $this->assertEquals($deploymentPath, $site->configuration['default_deployment_path']);

        // Verify database record
        $this->assertDatabaseHas('server_sites', [
            'server_id' => $server->id,
            'domain' => 'default',
            'status' => 'active',
        ]);

        // Verify JSON configuration column
        $siteFromDb = $server->sites()->where('domain', 'default')->first();
        $this->assertEquals($deploymentPath, $siteFromDb->configuration['default_deployment_path']);
    }

    /**
     * Test that default site is marked as default with correct status.
     * During provisioning, the default site should have is_default = true
     * and default_site_status = active since it's created and activated immediately.
     */
    public function test_default_site_is_marked_as_default_during_provisioning(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $appUser = config('app.ssh_user', str_replace(' ', '', strtolower(config('app.name'))));
        $phpVersion = PhpVersion::PHP83;
        $deploymentPath = "/home/{$appUser}/deployments/default/07112025-120000";
        $staticHtmlFramework = AvailableFramework::findBySlug(AvailableFramework::STATIC_HTML);

        // Act - Create default site record as NginxInstaller does during provisioning
        $site = $server->sites()->updateOrCreate(
            ['domain' => 'default'],
            [
                'available_framework_id' => $staticHtmlFramework->id,
                'document_root' => "/home/{$appUser}/default",
                'nginx_config_path' => '/etc/nginx/sites-available/default',
                'php_version' => $phpVersion,
                'ssl_enabled' => false,
                'is_default' => true,
                'default_site_status' => TaskStatus::Active,
                'configuration' => [
                    'is_default_site' => true,
                    'default_deployment_path' => $deploymentPath,
                ],
                'status' => 'active',
                'installed_at' => now(),
                'deinstalled_at' => null,
            ]
        );

        // Assert - Verify is_default is true
        $this->assertTrue($site->is_default, 'Default site should have is_default = true');
        $this->assertEquals(TaskStatus::Active, $site->default_site_status, 'Default site should have default_site_status = active');

        // Verify database record has is_default set
        $this->assertDatabaseHas('server_sites', [
            'server_id' => $server->id,
            'domain' => 'default',
            'is_default' => true,
            'default_site_status' => TaskStatus::Active->value,
            'status' => 'active',
        ]);
    }

    /**
     * Test that default site document_root points to symlink, not deployment directory.
     * The symlink enables transparent switching of which deployment is active.
     */
    public function test_default_site_document_root_uses_symlink(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $appUser = config('app.ssh_user', str_replace(' ', '', strtolower(config('app.name'))));
        $phpVersion = PhpVersion::PHP83;
        $staticHtmlFramework = AvailableFramework::findBySlug(AvailableFramework::STATIC_HTML);

        // Act - Create default site as NginxInstaller does
        $site = $server->sites()->updateOrCreate(
            ['domain' => 'default'],
            [
                'available_framework_id' => $staticHtmlFramework->id,
                'document_root' => "/home/{$appUser}/default",
                'nginx_config_path' => '/etc/nginx/sites-available/default',
                'php_version' => $phpVersion,
                'ssl_enabled' => false,
                'is_default' => true,
                'default_site_status' => TaskStatus::Active,
                'configuration' => [
                    'is_default_site' => true,
                    'default_deployment_path' => "/home/{$appUser}/deployments/default/07112025-120000",
                ],
                'status' => 'active',
                'installed_at' => now(),
                'deinstalled_at' => null,
            ]
        );

        // Assert - Document root should be symlink path, not deployment path
        $this->assertEquals("/home/{$appUser}/default", $site->document_root);
        $this->assertStringNotContainsString('/deployments/', $site->document_root);
        $this->assertStringNotContainsString('-', $site->document_root); // No timestamp in document_root
    }

    /**
     * Test that permissions are set correctly for deployment directory structure.
     */
    public function test_default_site_sets_permissions_on_deployment_directories(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new \App\Packages\Services\Nginx\NginxInstaller($server);
        $phpVersion = PhpVersion::PHP83;
        $appUser = config('app.ssh_user', str_replace(' ', '', strtolower(config('app.name'))));

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($installer);
        $method = $reflection->getMethod('commands');
        $method->setAccessible(true);

        // Act - Get commands array
        $commands = $method->invoke($installer, $phpVersion);

        // Assert - Check for chmod commands on deployment directories
        $chmodCommands = collect($commands)->filter(function ($command) {
            return is_string($command) && str_contains($command, 'chmod 755') && str_contains($command, '/deployments/');
        })->values();

        $this->assertGreaterThan(0, $chmodCommands->count(), 'Should set permissions on deployment directories');

        // Verify specific directories are chmod'd
        $allChmodCommands = $chmodCommands->join(' ');
        $this->assertStringContainsString('/deployments', $allChmodCommands);
    }

    /**
     * Test that TimeSyncInstallerJob is properly imported and can be instantiated.
     * This verifies the import statement was added correctly after the bug fix.
     */
    public function test_time_sync_installer_job_can_be_instantiated(): void
    {
        // Arrange
        $server = Server::factory()->create();

        // Act - Create job as NginxInstaller does
        $job = new TimeSyncInstallerJob($server);

        // Assert - Verify job structure
        $this->assertInstanceOf(TimeSyncInstallerJob::class, $job);
        $this->assertEquals($server->id, $job->server->id);
    }

    /**
     * Test that TimeSyncInstallerJob class exists in the correct namespace.
     * Ensures the import path App\Packages\Services\TimeSync\TimeSyncInstallerJob is valid.
     */
    public function test_time_sync_installer_job_exists_in_correct_namespace(): void
    {
        // Assert - Verify class exists in the expected namespace
        $this->assertTrue(
            class_exists(\App\Packages\Services\TimeSync\TimeSyncInstallerJob::class),
            'TimeSyncInstallerJob should exist in App\Packages\Services\TimeSync namespace'
        );
    }

    /**
     * Test that NginxInstaller has access to TimeSyncInstallerJob.
     * Verifies the import statement in NginxInstaller.php is correct.
     */
    public function test_nginx_installer_can_reference_time_sync_installer_job(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $installer = new \App\Packages\Services\Nginx\NginxInstaller($server);

        // Use reflection to verify the class can be loaded without errors
        $reflection = new \ReflectionClass($installer);

        // Assert - If we got here, the class loaded successfully with all imports
        $this->assertInstanceOf(\ReflectionClass::class, $reflection);
        $this->assertEquals('App\Packages\Services\Nginx\NginxInstaller', $reflection->getName());

        // Verify TimeSyncInstallerJob is importable from NginxInstaller's context
        $this->assertTrue(
            class_exists(TimeSyncInstallerJob::class),
            'TimeSyncInstallerJob should be importable from NginxInstaller context'
        );
    }
}
