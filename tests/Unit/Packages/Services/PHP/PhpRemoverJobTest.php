<?php

namespace Tests\Unit\Packages\Services\PHP;

use App\Enums\PhpStatus;
use App\Models\Server;
use App\Models\ServerPhp;
use App\Packages\Services\PHP\PhpRemoverJob;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhpRemoverJobTest extends TestCase
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
        $job = new PhpRemoverJob($server, $php->id);

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
        $job = new PhpRemoverJob($server, $php->id);

        // Assert
        $this->assertEquals(3, $job->tries);
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
        $job = new PhpRemoverJob($server, $php->id);
        $middleware = $job->middleware();

        // Assert
        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(\Illuminate\Queue\Middleware\WithoutOverlapping::class, $middleware[0]);
    }

    /**
     * Test job constructor accepts server and PHP ID.
     */
    public function test_constructor_accepts_server_and_php_id(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $phpId = 123;

        // Act
        $job = new PhpRemoverJob($server, $phpId);

        // Assert
        $this->assertInstanceOf(PhpRemoverJob::class, $job);
        $this->assertEquals($server->id, $job->server->id);
        $this->assertEquals($phpId, $job->phpId);
    }

    /**
     * Test failed() method updates status to PhpStatus::Failed.
     */
    public function test_failed_method_updates_status_to_failed(): void
    {
        // Arrange
        $server = Server::factory()->create();
        $php = ServerPhp::factory()->create([
            'server_id' => $server->id,
            'status' => PhpStatus::Active,
        ]);

        $job = new PhpRemoverJob($server, $php->id);
        $exception = new Exception('Removal failed');

        // Act
        $job->failed($exception);

        // Assert
        $php->refresh();
        $this->assertEquals(PhpStatus::Failed, $php->status);
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
            'status' => PhpStatus::Active,
            'error_log' => null,
        ]);

        $job = new PhpRemoverJob($server, $php->id);
        $errorMessage = 'PHP removal timeout error';
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
        $nonExistentId = 99999;

        $job = new PhpRemoverJob($server, $nonExistentId);
        $exception = new Exception('Test error');

        // Act - should not throw exception
        $job->failed($exception);

        // Assert - verify no PHP record was created
        $this->assertDatabaseMissing('server_phps', [
            'id' => $nonExistentId,
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
            'status' => PhpStatus::Active,
            'version' => '8.3',
        ]);

        $job = new PhpRemoverJob($server, $php->id);
        $exception = new Exception('Removal error');

        // Act
        $job->failed($exception);

        // Assert - verify other fields remain unchanged
        $php->refresh();
        $this->assertEquals('8.3', $php->version);
        $this->assertEquals($server->id, $php->server_id);
    }
}
