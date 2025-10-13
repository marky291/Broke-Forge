<?php

namespace Tests\Unit\Packages\Services\Nginx;

use App\Enums\ScheduleFrequency;
use App\Models\Server;
use App\Packages\Enums\PhpVersion;
use App\Packages\Services\Nginx\NginxInstaller;
use App\Packages\Services\Scheduler\ServerSchedulerInstallerJob;
use App\Packages\Services\Scheduler\Task\ServerScheduleTaskInstallerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NginxInstallerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_task_installer_job_with_correct_data(): void
    {
        Queue::fake();

        $server = Server::factory()->create();

        // Create firewall (required by NginxInstaller)
        \App\Models\ServerFirewall::factory()->for($server)->create();

        // Create a partial mock of NginxInstaller to prevent SSH connection
        $installer = $this->getMockBuilder(NginxInstaller::class)
            ->setConstructorArgs([$server])
            ->onlyMethods(['install'])
            ->getMock();

        // Mock the install method to prevent SSH connections
        $installer->expects($this->once())
            ->method('install');

        // Execute the installer
        $installer->execute(PhpVersion::PHP84);

        // Verify the scheduler installer job was dispatched
        Queue::assertPushed(ServerSchedulerInstallerJob::class);

        // Verify the task installer job was dispatched with correct array data
        Queue::assertPushed(ServerScheduleTaskInstallerJob::class, function ($job) use ($server) {
            return $job->server->is($server)
                && is_array($job->taskDataOrModel)
                && $job->taskDataOrModel['name'] === 'Remove unused packages'
                && $job->taskDataOrModel['command'] === 'apt-get autoremove && apt-get autoclean'
                && $job->taskDataOrModel['frequency'] === ScheduleFrequency::Weekly;
        });
    }
}
