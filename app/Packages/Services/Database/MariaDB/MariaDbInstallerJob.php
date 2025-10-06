<?php

namespace App\Packages\Services\Database\MariaDB;

use App\Models\Server;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * MariaDB Installation Job
 *
 * Handles queued MariaDB installation on remote servers
 */
class MariaDbInstallerJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600;

    public function __construct(
        public Server $server
    ) {}

    public function handle(): void
    {
        $database = $this->server->databases()->latest()->first();

        Log::info("Starting MariaDB installation for server #{$this->server->id}", [
            'version' => $database?->version ?? 'unknown',
        ]);

        $installer = new MariaDbInstaller($this->server);
        // Execute installation - base class handles failure marking automatically
        $installer->execute();

        Log::info("MariaDB installation completed for server #{$this->server->id}");
    }
}
