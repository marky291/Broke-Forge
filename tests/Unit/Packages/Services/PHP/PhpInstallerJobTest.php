<?php

namespace Tests\Unit\Packages\Services\PHP;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerPhp;
use App\Packages\Services\PHP\PhpInstallerJob;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhpInstallerJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test job has correct timeout property.
     */
    public function test_job_has_correct_timeout_property(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $php = ServerPhp::factory()->create(['server_id' => $server->id]);

        // Act
        $job = new PhpInstallerJob($server, $php);

        // Assert
        $this->assertEquals(600, $job->timeout);
    }

    /**
     * Test job has correct tries property.
     */
    public function test_job_has_correct_tries_property(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $php = ServerPhp::factory()->create(['server_id' => $server->id]);

        // Act
        $job = new PhpInstallerJob($server, $php);

        // Assert
        $this->assertEquals(0, $job->tries);
    }

    /**
     * Test job has correct maxExceptions property.
     */
    public function test_job_has_correct_max_exceptions_property(): void
    {
        $server = Server::factory()->create();
        $php = ServerPhp::factory()->create(['server_id' => $server->id]);

        // Act
        $job = new PhpInstallerJob($server, $php);
        $this->assertEquals(3, $job->maxExceptions);
    }

    /**
     * Test middleware is configured with WithoutOverlapping.
     */
    public function test_middleware_configured_with_without_overlapping(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $php = ServerPhp::factory()->create(['server_id' => $server->id]);

        // Act
        $job = new PhpInstallerJob($server, $php);
        $middleware = $job->middleware();

        // Assert
        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(\Illuminate\Queue\Middleware\WithoutOverlapping::class, $middleware[0]);
    }

    /**
     * Test job constructor accepts server and PHP.
     */
    public function test_constructor_accepts_server_and_php(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $php = ServerPhp::factory()->create(['server_id' => $server->id]);

        // Act
        $job = new PhpInstallerJob($server, $php);

        // Assert
        $this->assertInstanceOf(PhpInstallerJob::class, $job);
        $this->assertEquals($server->id, $job->server->id);
        $this->assertEquals($php->id, $job->serverPhp->id);
    }

    /**
     * Test failed() method updates status to TaskStatus::Failed.
     */
    public function test_failed_method_updates_status_to_failed(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $php = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Installing,
        ]);

        $job = new PhpInstallerJob($server, $php);
        $exception = new Exception('Installation failed');

        // Act
        $job->failed($exception);

        // Assert
        $php->refresh();
        $this->assertEquals(TaskStatus::Failed, $php->status);
    }

    /**
     * Test failed() method stores error message.
     */
    public function test_failed_method_stores_error_log(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $php = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Installing,
            'error_log' => null,
        ]);

        $job = new PhpInstallerJob($server, $php);
        $errorMessage = 'PHP installation timeout error';
        $exception = new Exception($errorMessage);

        // Act
        $job->failed($exception);

        // Assert
        $php->refresh();
        $this->assertEquals($errorMessage, $php->error_log);
    }

    /**
     * Test failed() method handles missing records gracefully.
     */
    public function test_failed_method_handles_missing_records_gracefully(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $php = ServerPhp::factory()->create(['server_id' => $server->id]);

        $job = new PhpInstallerJob($server, $php);
        $phpId = $php->id;
        $php->delete(); // Now fresh() will return null

        $exception = new Exception('Test error');

        // Act - should not throw exception
        $job->failed($exception);

        // Assert - verify the PHP was deleted
        $this->assertDatabaseMissing('server_phps', [
            'id' => $phpId,
        ]);
    }

    /**
     * Test failed() method preserves PHP record data except status and error.
     */
    public function test_failed_method_preserves_php_record_data(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $php = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'status' => TaskStatus::Installing,
            'version' => '8.3',
        ]);

        $job = new PhpInstallerJob($server, $php);
        $exception = new Exception('Installation error');

        // Act
        $job->failed($exception);

        // Assert - verify other fields remain unchanged
        $php->refresh();
        $this->assertEquals('8.3', $php->version);
        $this->assertEquals($server->id, $php->server_id);
    }
}
