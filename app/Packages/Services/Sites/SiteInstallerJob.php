<?php

namespace App\Packages\Services\Sites;

use App\Enums\TaskStatus;
use App\Models\Server;
use App\Models\ServerSite;
use App\Packages\Services\Sites\Framework\GenericPhp\GenericPhpInstallerJob;
use App\Packages\Services\Sites\Framework\Laravel\LaravelInstallerJob;
use App\Packages\Services\Sites\Framework\StaticHtml\StaticHtmlInstallerJob;
use App\Packages\Services\Sites\Framework\WordPress\WordPressInstallerJob;
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
     * The number of seconds the job can run before timing out.
     */
    public $timeout = 600;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 0;

    /**
     * The number of exceptions to allow before failing.
     */
    public $maxExceptions = 1;

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

        // Route to appropriate framework installer based on available_framework_id
        // Framework IDs from AvailableFrameworkSeeder:
        // 1 = Laravel
        // 2 = WordPress
        // 3 = Generic PHP
        // 4 = Static HTML
        match ($site->available_framework_id) {
            1 => LaravelInstallerJob::dispatchSync($this->server, $this->siteId),
            2 => WordPressInstallerJob::dispatchSync($this->server, $this->siteId),
            3 => GenericPhpInstallerJob::dispatchSync($this->server, $this->siteId),
            4 => StaticHtmlInstallerJob::dispatchSync($this->server, $this->siteId),
            default => throw new \RuntimeException("Unknown framework ID: {$site->available_framework_id}"),
        };

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
