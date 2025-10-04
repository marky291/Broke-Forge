<?php

namespace App\Packages\Services\Database\PostgreSQL;

use App\Models\Server;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PostgreSqlInstallerJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Server $server
    ) {}

    public function handle(): void
    {
        Log::info("Starting PostgreSQL installation for server #{$this->server->id}");

        try {
            $installer = new PostgreSqlInstaller($this->server);
            $installer->execute();

            Log::info("PostgreSQL installation completed for server #{$this->server->id}");
        } catch (\Exception $e) {
            Log::error("PostgreSQL installation failed for server #{$this->server->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
