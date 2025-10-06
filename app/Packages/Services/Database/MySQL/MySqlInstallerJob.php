<?php

namespace App\Packages\Services\Database\MySQL;

use App\Models\Server;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * MySQL Database Installation Job
 *
 * Handles queued MySQL database installation on remote servers
 */
class MySqlInstallerJob implements ShouldQueue
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
        Log::info("Starting MySQL database installation for server #{$this->server->id}");

        // Create installer instance
        $installer = new MySqlInstaller($this->server);

        // Execute installation - base class handles failure marking automatically
        $installer->execute();

        Log::info("MySQL database installation completed for server #{$this->server->id}");
    }
}
