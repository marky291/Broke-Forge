<?php

namespace App\Packages\Services\Sites;

use App\Models\Server;
use App\Models\ServerSite;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

/**
 * Site Remover Job
 *
 * Handles queued site removal from remote servers
 */
class SiteRemoverJob implements ShouldQueue
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
    public $tries = 0;

    /**
     * The number of exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    public function __construct(
        public Server $server,
        public ServerSite $site
    ) {}

    public function handle(): void
    {
        set_time_limit(0);

        Log::info("Starting site uninstallation for site #{$this->site->id} on server #{$this->server->id}");

        try {
            // Mark as uninstalling
            $this->site->update([
                'status' => 'removing',
            ]);

            // Create remover instance
            $remover = new SiteRemover($this->server);

            // Execute removal
            $remover->execute([
                'site' => $this->site,
                'domain' => $this->site->domain,
            ]);

            // Delete the site record after successful removal
            $this->site->delete();

            Log::info("Site uninstallation completed for site #{$this->site->id}");

        } catch (Exception $e) {
            // Mark as failed
            $this->site->update([
                'status' => 'failed',
                'error_log' => $e->getMessage(),
            ]);

            Log::error("Site uninstallation failed for site #{$this->site->id}", [
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
        $site = ServerSite::find($this->site->id);

        if ($site) {
            $site->update([
                'status' => 'failed',
                'error_log' => $exception->getMessage(),
            ]);
        }

        Log::error('SiteRemoverJob failed', [
            'site_id' => $this->site->id,
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
