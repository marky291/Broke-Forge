<?php

namespace Tests\Unit\Packages\Services\Nginx;

use App\Models\Server;
use App\Packages\Enums\PhpVersion;
use App\Packages\Orchestratable;
use App\Packages\Services\Nginx\NginxInstallerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Tests\TestCase;

class NginxInstallerJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_extends_orchestratable(): void
    {
        $server = Server::factory()->create();
        $phpVersion = PhpVersion::PHP83;
        $job = new NginxInstallerJob($server, $phpVersion, false);
        $this->assertInstanceOf(Orchestratable::class, $job);
    }

    public function test_job_has_correct_timeout_property(): void
    {
        $server = Server::factory()->create();
        $phpVersion = PhpVersion::PHP83;
        $job = new NginxInstallerJob($server, $phpVersion, false);
        $this->assertEquals(600, $job->timeout);
    }

    public function test_job_has_correct_tries_property(): void
    {
        $server = Server::factory()->create();
        $phpVersion = PhpVersion::PHP83;
        $job = new NginxInstallerJob($server, $phpVersion, false);
        $this->assertEquals(1, $job->tries);
    }

    /**
     * Test job has correct maxExceptions property.
     */
    public function test_job_has_correct_max_exceptions_property(): void
    {
        $server = Server::factory()->create();
        $phpVersion = PhpVersion::PHP83;
        $job = new NginxInstallerJob($server, $phpVersion, false);
        $this->assertEquals(3, $job->maxExceptions);
    }

    public function test_middleware_uses_without_overlapping(): void
    {
        $server = Server::factory()->create();
        $phpVersion = PhpVersion::PHP83;
        $job = new NginxInstallerJob($server, $phpVersion, false);
        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
    }

    public function test_constructor_accepts_parameters(): void
    {
        $server = Server::factory()->create();
        $phpVersion = PhpVersion::PHP83;
        $job = new NginxInstallerJob($server, $phpVersion, false);
        $this->assertInstanceOf(NginxInstallerJob::class, $job);
        $this->assertEquals($server->id, $job->server->id);
    }

    public function test_server_property_is_accessible(): void
    {
        $server = Server::factory()->create();
        $phpVersion = PhpVersion::PHP83;
        $job = new NginxInstallerJob($server, $phpVersion, false);
        $this->assertInstanceOf(Server::class, $job->server);
        $this->assertEquals($server->id, $job->server->id);
    }

    public function test_php_version_property_is_accessible(): void
    {
        $server = Server::factory()->create();
        $phpVersion = PhpVersion::PHP83;
        $job = new NginxInstallerJob($server, $phpVersion, false);
        $this->assertEquals($phpVersion, $job->phpVersion);
    }

    public function test_is_provisioning_server_property_is_accessible(): void
    {
        $server = Server::factory()->create();
        $phpVersion = PhpVersion::PHP83;

        $job = new NginxInstallerJob($server, $phpVersion, true);
        $this->assertTrue($job->isProvisioningServer);

        $job2 = new NginxInstallerJob($server, $phpVersion, false);
        $this->assertFalse($job2->isProvisioningServer);
    }

    public function test_failed_method_updates_provision_state_with_failed_status(): void
    {
        // Arrange
        $server = Server::factory()->create([
            'provision_status' => \App\Enums\TaskStatus::Installing,
            'provision_state' => collect([
                1 => 'success',
                2 => 'success',
                3 => 'success',
                4 => 'success',
                5 => 'success',
                6 => 'installing', // Current step
                7 => 'pending',
                8 => 'pending',
            ]),
        ]);

        $job = new NginxInstallerJob($server, PhpVersion::PHP83, true);
        $exception = new \Exception('Test exception');

        // Act
        $job->failed($exception);

        // Assert
        $server->refresh();
        $this->assertEquals(\App\Enums\TaskStatus::Failed, $server->provision_status);
        $this->assertEquals('failed', $server->provision_state->get(6));
    }

    public function test_failed_method_marks_first_installing_step_as_failed(): void
    {
        // Arrange - Multiple installing steps (edge case)
        $server = Server::factory()->create([
            'provision_status' => \App\Enums\TaskStatus::Installing,
            'provision_state' => collect([
                1 => 'success',
                2 => 'installing', // This should be marked as failed
                3 => 'pending',
            ]),
        ]);

        $job = new NginxInstallerJob($server, PhpVersion::PHP83, true);
        $exception = new \Exception('Test exception');

        // Act
        $job->failed($exception);

        // Assert
        $server->refresh();
        $this->assertEquals('failed', $server->provision_state->get(2));
    }

    public function test_failed_method_handles_no_installing_step_gracefully(): void
    {
        // Arrange - No installing step found
        $server = Server::factory()->create([
            'provision_status' => \App\Enums\TaskStatus::Pending,
            'provision_state' => collect([
                1 => 'success',
                2 => 'success',
                3 => 'pending',
            ]),
        ]);

        $job = new NginxInstallerJob($server, PhpVersion::PHP83, true);
        $exception = new \Exception('Test exception');

        // Act
        $job->failed($exception);

        // Assert - Should not crash, just update provision_status
        $server->refresh();
        $this->assertEquals(\App\Enums\TaskStatus::Failed, $server->provision_status);
    }

    public function test_failed_method_does_not_update_provision_state_when_not_provisioning(): void
    {
        // Arrange
        $server = Server::factory()->create([
            'provision_status' => \App\Enums\TaskStatus::Success,
            'provision_state' => collect([
                1 => 'success',
                2 => 'success',
            ]),
        ]);

        $job = new NginxInstallerJob($server, PhpVersion::PHP83, false); // Not provisioning
        $exception = new \Exception('Test exception');

        // Act
        $job->failed($exception);

        // Assert - Should not update anything
        $server->refresh();
        $this->assertEquals(\App\Enums\TaskStatus::Success, $server->provision_status);
        $this->assertEquals('success', $server->provision_state->get(1));
    }
}
