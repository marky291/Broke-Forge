<?php

namespace App\Packages\Services\Sites;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerSite;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

/**
 * Site Installation Router Job
 *
 * Routes site installation to the appropriate framework-specific installer.
 * This is a lightweight dispatcher that simply determines which framework
 * installer to use based on the site's framework configuration.
 */
class SiteInstallerJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Server $server,
        public int $siteId
    ) {}

    /**
     * Execute the job by dispatching to the appropriate framework installer.
     */
    public function handle(): void
    {
        // Load the site record
        $site = ServerSite::findOrFail($this->siteId);

        Log::info("Routing site installation for site #{$site->id} (domain: {$site->domain}, framework: {$site->siteFramework->slug}) on server #{$this->server->id}");

        // Route to appropriate framework installer based on framework slug
        $installerClass = $site->siteFramework->getInstallerClass();
        $installerClass::dispatchSync($this->server, $this->siteId);

        Log::info("Site installation job dispatched for site #{$site->id}");
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

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $site = ServerSite::find($this->siteId);

        if ($site) {
            $site->update([
                'status' => TaskStatus::Failed->value,
                'error_log' => $exception->getMessage(),
            ]);
        }

        Log::error('SiteInstallerJob routing failed', [
            'site_id' => $this->siteId,
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
