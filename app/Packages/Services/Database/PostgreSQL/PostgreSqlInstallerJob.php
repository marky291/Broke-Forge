<?php

namespace App\Packages\Services\Database\PostgreSQL;

use App\Models\Server;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PostgreSqlInstallerJob implements ShouldQueue
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
        Log::info("Starting PostgreSQL installation for server #{$this->server->id}");

        $installer = new PostgreSqlInstaller($this->server);
        // Execute installation - base class handles failure marking automatically
        $installer->execute();

        Log::info("PostgreSQL installation completed for server #{$this->server->id}");
    }
}
