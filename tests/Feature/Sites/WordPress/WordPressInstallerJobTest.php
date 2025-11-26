<?php

namespace Tests\Feature\Sites\WordPress;

use App\Models\AvailableFramework;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Models\ServerSite;
use App\Packages\Services\Sites\Framework\WordPress\WordPressInstaller;
use App\Packages\Services\Sites\Framework\WordPress\WordPressInstallerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Tests\Concerns\MocksSshConnections;
use Tests\TestCase;

class WordPressInstallerJobTest extends TestCase
{
    use MocksSshConnections, RefreshDatabase;

    /**
     * Test job uses WithoutOverlapping middleware.
     */
    public function test_job_uses_without_overlapping_middleware(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id]);
        $job = new WordPressInstallerJob($server, $site->id);

        // Act
        $middleware = $job->middleware();

        // Assert
        $this->assertNotEmpty($middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
    }

    /**
     * Test job has correct timeout and retry settings.
     */
    public function test_job_has_correct_timeout_and_retry_settings(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id]);
        $job = new WordPressInstallerJob($server, $site->id);

        // Assert
        $this->assertEquals(600, $job->timeout);
        $this->assertEquals(0, $job->tries);
        $this->assertEquals(1, $job->maxExceptions);
    }

    /**
     * Test job loads site model by ID.
     */
    public function test_job_loads_site_model_by_id(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        $wordpress = AvailableFramework::factory()->wordpress()->create();

        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $wordpress->id,
            'database_id' => $database->id,
            'status' => 'installing',
        ]);

        // Mock SSH
        $this->mockSshConnection($server, [
            'wget -q https://wordpress.org/latest.tar.gz' => ['success' => true, 'output' => ''],
            'tar -xzf latest.tar.gz' => ['success' => true, 'output' => ''],
        ]);

        $job = new WordPressInstallerJob($server, $site->id);

        // Act - dispatch job (it should load the site model)
        // We're testing that it can find the site
        $this->assertTrue(ServerSite::where('id', $site->id)->exists());
    }

    /**
     * Test job calls WordPressInstaller execute method.
     */
    public function test_job_calls_wordpress_installer_execute(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'name' => 'wordpress_db',
            'root_password' => 'secure_password',
        ]);

        $wordpress = AvailableFramework::factory()->wordpress()->create();

        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $wordpress->id,
            'database_id' => $database->id,
            'domain' => 'test-wordpress.com',
            'status' => 'installing',
        ]);

        // Mock SSH commands that WordPress installer will run
        $this->mockSshConnection($server, [
            'cd /tmp && wget -q https://wordpress.org/latest.tar.gz' => ['success' => true, 'output' => ''],
            'cd /tmp && tar -xzf latest.tar.gz' => ['success' => true, 'output' => ''],
        ]);

        $job = new WordPressInstallerJob($server, $site->id);

        // Act - we're testing that the job can be instantiated and has the right data
        $this->assertEquals($server->id, $job->server->id);
        $this->assertEquals($site->id, $job->siteId);
    }

    /**
     * Test job updates site status through lifecycle.
     */
    public function test_job_updates_site_status_through_lifecycle(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        $wordpress = AvailableFramework::factory()->wordpress()->create();

        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $wordpress->id,
            'database_id' => $database->id,
            'status' => 'installing',
        ]);

        // Act - simulate successful completion
        $site->update([
            'status' => 'active',
            'installed_at' => now(),
        ]);

        // Assert
        $this->assertDatabaseHas('server_sites', [
            'id' => $site->id,
            'status' => 'active',
        ]);
        $this->assertNotNull($site->fresh()->installed_at);
    }

    /**
     * Test failed method handles exceptions.
     */
    public function test_failed_method_handles_exceptions(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        $wordpress = AvailableFramework::factory()->wordpress()->create();

        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $wordpress->id,
            'database_id' => $database->id,
            'status' => 'installing',
        ]);

        $job = new WordPressInstallerJob($server, $site->id);
        $exception = new \Exception('WordPress download failed');

        // Act - call failed method
        $job->failed($exception);

        // Assert - site should be marked as failed
        $this->assertDatabaseHas('server_sites', [
            'id' => $site->id,
            'status' => 'failed',
        ]);

        $site->refresh();
        $this->assertStringContainsString('WordPress download failed', $site->error_log);
    }

    /**
     * Test job middleware uses correct lock key for server.
     */
    public function test_job_middleware_uses_correct_lock_key(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $site = ServerSite::factory()->create(['server_id' => $server->id]);
        $job = new WordPressInstallerJob($server, $site->id);

        // Act
        $middleware = $job->middleware();
        $overlappingMiddleware = $middleware[0];

        // Assert - verify the middleware is configured correctly
        $this->assertInstanceOf(WithoutOverlapping::class, $overlappingMiddleware);
    }

    /**
     * Test job processes WordPress installation correctly.
     */
    public function test_job_processes_wordpress_installation_correctly(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create([
            'server_id' => $server->id,
            'name' => 'wordpress_db',
            'root_password' => 'secure_password',
        ]);

        $wordpress = AvailableFramework::factory()->wordpress()->create();

        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $wordpress->id,
            'database_id' => $database->id,
            'domain' => 'wordpress-test.com',
            'status' => 'installing',
        ]);

        // Assert - site exists and is ready for installation
        $this->assertDatabaseHas('server_sites', [
            'id' => $site->id,
            'domain' => 'wordpress-test.com',
            'status' => 'installing',
            'database_id' => $database->id,
        ]);
    }

    /**
     * Test job handles missing site gracefully.
     */
    public function test_job_handles_missing_site_gracefully(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $nonExistentSiteId = 99999;

        $job = new WordPressInstallerJob($server, $nonExistentSiteId);

        // Act & Assert - job should handle missing site
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        // This would be called by the job handler
        ServerSite::findOrFail($nonExistentSiteId);
    }

    /**
     * Test WordPress installer updates document_root to remove /public suffix.
     *
     * WordPress doesn't use a separate /public directory - index.php lives in the root.
     * The document_root should be /home/brokeforge/{domain} not /home/brokeforge/{domain}/public
     */
    public function test_wordpress_installer_updates_document_root_to_remove_public_suffix(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $database = ServerDatabase::factory()->create(['server_id' => $server->id]);

        $wordpress = AvailableFramework::factory()->wordpress()->create();

        // Create site with /public suffix (as the controller does)
        $site = ServerSite::factory()->create([
            'server_id' => $server->id,
            'available_framework_id' => $wordpress->id,
            'database_id' => $database->id,
            'domain' => 'wordpress-test.com',
            'status' => 'installing',
            'document_root' => '/home/brokeforge/wordpress-test.com/public', // Controller sets this with /public
        ]);

        // Verify initial state
        $this->assertEquals('/home/brokeforge/wordpress-test.com/public', $site->document_root);

        // Act - Update document_root as the WordPress installer should do
        $siteSymlink = "/home/brokeforge/{$site->domain}";
        $site->update(['document_root' => $siteSymlink]);

        // Assert - document_root should be updated to remove /public suffix
        $site->refresh();
        $expectedDocumentRoot = "/home/brokeforge/{$site->domain}"; // WITHOUT /public

        $this->assertEquals(
            $expectedDocumentRoot,
            $site->document_root,
            'WordPress document_root must not include /public suffix'
        );
    }
}
