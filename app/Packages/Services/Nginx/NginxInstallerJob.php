<?php

namespace App\Packages\Services\Nginx;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Packages\Enums\PhpVersion;
use App\Packages\Orchestratable;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Nginx Installation Orchestrator Job
 *
 * Orchestrates Nginx web server installation by coordinating multiple sub-installer jobs.
 * Extends Orchestratable to use separate lock key (orchestrator:{server_id}) preventing
 * lock contention with child jobs that use package:action:{server_id}.
 */
class NginxInstallerJob extends Orchestratable
{
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
                $this->server->update(['provision_status' => TaskStatus::Installing]);
            }

            // Execute installation - the installer handles all logic, database tracking, and dependencies
            $installer->execute($this->phpVersion);

            Log::info("Nginx installation completed for server #{$this->server->id}");

            if ($this->isProvisioningServer) {
                $this->server->update(['provision_status' => TaskStatus::Success]);
            }

        } catch (Exception $e) {
            if ($this->isProvisioningServer) {
                $this->server->update(['provision_status' => TaskStatus::Failed]);
            }
            Log::error("Nginx installation failed for server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->server->refresh();

        if ($this->server && $this->isProvisioningServer) {
            $this->server->update([
                'provision_status' => TaskStatus::Failed,
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
