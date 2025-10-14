<?php

namespace App\Packages\Services\Database\PostgreSQL;

use App\Enums\DatabaseStatus;
use App\Models\Server;
use App\Models\ServerDatabase;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * PostgreSQL Installation Job
 *
 * Handles queued PostgreSQL installation on remote servers with real-time status updates
 */
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
        public Server $server,
        public int $databaseId  // ← Receives database record ID
    ) {}

    public function handle(): void
    {
        // Set no time limit for long-running installation process
        set_time_limit(0);

        // Load the database record from database
        $database = ServerDatabase::findOrFail($this->databaseId);

        Log::info('Starting PostgreSQL installation', [
            'database_id' => $database->id,
            'server_id' => $this->server->id,
            'version' => $database->version,
        ]);

        try {
            // ✅ UPDATE: pending → installing
            $database->update(['status' => DatabaseStatus::Installing]);
            // Model event broadcasts automatically via Reverb

            // Create installer instance
            $installer = new PostgreSqlInstaller($this->server);

            // Execute installation
            $installer->execute();

            // ✅ UPDATE: installing → active
            $database->update(['status' => DatabaseStatus::Active]);
            // Model event broadcasts automatically via Reverb

            Log::info('PostgreSQL installation completed', [
                'database_id' => $database->id,
                'server_id' => $this->server->id,
            ]);

        } catch (Exception $e) {
            // ✅ UPDATE: any → failed
            $database->update(['status' => DatabaseStatus::Failed]);
            // Model event broadcasts automatically via Reverb

            Log::error('PostgreSQL installation failed', [
                'database_id' => $database->id,
                'server_id' => $this->server->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;  // Re-throw for Laravel's retry mechanism
        }
    }
}
