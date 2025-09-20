<?php

namespace App\Jobs;

use App\Models\Server;
use App\Models\ServerService;
use App\Provision\Enums\ProvisionStatus;
use App\Provision\Enums\ServerType;
use App\Provision\Enums\ServiceType;
use App\Provision\Server\WebServer\WebServiceProvision;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProvisionWebServiceJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Server $server,
    ) {}

    /**
     * Execute the job to provision web services on the server.
     */
    public function handle(): void
    {
        Log::info("Starting web service provisioning for server #{$this->server->id}");

        try {
            // Create the web service provisioner
            $provisioner = new WebServiceProvision($this->server);

            // Get PHP version from existing PHP service or use default
            $phpVersion = $this->getPhpVersion();

            // Create or update the web service record in database
            $service = ServerService::updateOrCreate(
                [
                    'server_id' => $this->server->id,
                    'service_name' => 'web',
                ],
                [
                    'service_type' => ServiceType::SERVER,
                    'configuration' => [
                        'nginx_version' => 'latest',
                        'php_version' => $phpVersion,
                    ],
                    'status' => 'installing',
                ]
            );

            // Run the installation
            $provisioner->provision();

            // Mark service as active after successful provisioning
            $service->status = 'active';
            $service->save();

            // Persist the default Nginx site now that provisioning succeeded
            $this->server->sites()->updateOrCreate(
                ['domain' => 'default'],
                [
                    'document_root' => '/var/www/html',
                    'nginx_config_path' => '/etc/nginx/sites-available/default',
                    'php_version' => $phpVersion,
                    'ssl_enabled' => false,
                    'configuration' => ['is_default_site' => true],
                    'status' => 'active',
                    'provisioned_at' => now(),
                    'deprovisioned_at' => null,
                ]
            );

            // set service as active for PHP, since it was installed with nginx
            $this->server->services()->where('service_name', 'php')->update(['status' => 'active']);

            // Update server type and provision status after successful provisioning
            $this->server->server_type = ServerType::WebServer;
            $this->server->provision_status = ProvisionStatus::Completed;
            $this->server->save();

            Log::info("Web service provisioning completed for server #{$this->server->id}");
        } catch (\Exception $e) {
            Log::error("Web service provisioning failed for server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update service status if it exists
            if (isset($service)) {
                $service->status = 'failed';
                $service->save();
            }

            // Mark server as failed if web service provisioning fails
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
