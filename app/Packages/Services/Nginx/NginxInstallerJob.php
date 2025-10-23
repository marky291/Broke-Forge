<?php

namespace App\Packages\Services\Nginx;

use App\Models\Server;
use App\Packages\Enums\PhpVersion;
use App\Packages\Enums\ProvisionStatus;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Nginx Installation Job
 *
 * Handles queued Nginx web server installation on remote servers
 */
class NginxInstallerJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    public function __construct(
        public Server $server,
        public PhpVersion $phpVersion,
        public bool $isProvisioningServer = false
    ) {}

    public function handle(): void
    {
        // Set no time limit for long-running installation process
        set_time_limit(0);

        Log::info("Starting Nginx installation for server #{$this->server->id} with PHP {$this->phpVersion->value}");

        try {
            // Create installer instance
            $installer = new NginxInstaller($this->server);

            if ($this->isProvisioningServer) {
                $this->server->update(['provision_status' => ProvisionStatus::Installing]);
            }

            // Execute installation - the installer handles all logic, database tracking, and dependencies
            $installer->execute($this->phpVersion);

            Log::info("Nginx installation completed for server #{$this->server->id}");

            if ($this->isProvisioningServer) {
                $this->server->update(['provision_status' => ProvisionStatus::Completed]);
            }

        } catch (Exception $e) {
            if ($this->isProvisioningServer) {
                $this->server->update(['provision_status' => ProvisionStatus::Failed]);
            }
            Log::error("Nginx installation failed for server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("package:action:{$this->server->id}"))->shared()
                ->releaseAfter(15)
                ->expireAfter(900),
        ];
    }

    public function failed(\Throwable $exception): void
    {
        $this->server->refresh();

        if ($this->server && $this->isProvisioningServer) {
            $this->server->update([
                'provision_status' => ProvisionStatus::Failed,
            ]);
        }

        Log::error('NginxInstallerJob job failed', [
            'server_id' => $this->server->id,
            'php_version' => $this->phpVersion->value,
            'is_provisioning_server' => $this->isProvisioningServer,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
