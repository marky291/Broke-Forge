<?php

namespace App\Packages\Services\Sites;

use App\Models\Server;
use App\Models\ServerSite;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

/**
 * Site Installation Job
 *
 * Handles queued site installation on remote servers
 */
class ProvisionedSiteInstallerJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out.
     */
    public $timeout = 600;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 0;

    /**
     * The number of exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Server $server,
        public int $siteId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        set_time_limit(0);

        // Load the site record
        $site = ServerSite::findOrFail($this->siteId);

        Log::info("Starting site installation for site #{$site->id} (domain: {$site->domain}) on server #{$this->server->id}");

        try {
            // Create installer instance
            $installer = new ProvisionedSiteInstaller($this->server);

            // Execute installation - the installer handles all logic and database tracking
            $installer->execute($this->siteId);

            Log::info("Site installation completed for site #{$site->id} on server #{$this->server->id}");
        } catch (\Exception $e) {
            // Update status to failed and capture error details
            $site->update([
                'status' => 'failed',
                'error_log' => $e->getMessage(),
            ]);

            Log::error("Site installation failed for site #{$site->id} on server #{$this->server->id}", [
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
        $site = ServerSite::find($this->siteId);

        if ($site) {
            $site->update([
                'status' => 'failed',
                'error_log' => $exception->getMessage(),
            ]);
        }

        Log::error('ProvisionedSiteInstallerJob failed', [
            'site_id' => $this->siteId,
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
