<?php

namespace App\Packages\Services\Nginx;

use App\Models\Server;
use App\Models\ServerService;
use App\Packages\Enums\ProvisionStatus;
use App\Packages\Enums\ServerType;
use App\Packages\Enums\ServiceType;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Job for installing web services (NGINX + PHP) on servers
 */
class NginxInstallerJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Server $server,
    ) {}

    /**
     * Execute the job to install web services on the server.
     */
    public function handle(): void
    {
        Log::info("Starting web service installation for server #{$this->server->id}");

        try {
            // Create the web service installer
            $installer = new NginxInstaller($this->server);

            // Get PHP version from existing PHP service or use default
            $phpVersion = $this->getPhpVersion();

            // Create or update the web service record in database
            $service = ServerService::updateOrCreate(
                [
                    'server_id' => $this->server->id,
                    'service_name' => 'web',
                ],
                [
                    'service_type' => ServiceType::WEBSERVER,
                    'configuration' => [
                        'nginx_version' => 'latest',
                        'php_version' => $phpVersion,
                    ],
                    'status' => 'installing',
                ]
            );

            // Run the installation
            $installer->execute(['php_version' => $phpVersion]);

            // Mark service as active after successful installation
            $service->status = 'active';
            $service->save();

            // set service as active for PHP, since it was installed with nginx
            $this->server->services()->where('service_name', 'php')->update(['status' => 'active']);

            // Update server type and provision status after successful installation
            $this->server->server_type = ServerType::WebServer;
            $this->server->provision_status = ProvisionStatus::Completed;
            $this->server->save();

            Log::info("Web service installation completed for server #{$this->server->id}");
        } catch (\Exception $e) {
            Log::error("Web service installation failed for server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update service status if it exists
            if (isset($service)) {
                $service->status = 'failed';
                $service->save();
            }

            // Mark server as failed if web service installation fails
            $this->server->connection = 'failed';
            $this->server->provision_status = ProvisionStatus::Failed;
            $this->server->save();

            throw $e;
        }
    }

    /**
     * Get PHP version from configuration or default
     */
    protected function getPhpVersion(): string
    {
        $phpService = $this->server->services()->where('service_name', 'php')->latest('id')->first();

        if ($phpService && is_array($phpService->configuration) && isset($phpService->configuration['version'])) {
            return (string) $phpService->configuration['version'];
        }

        return '8.3';
    }
}
