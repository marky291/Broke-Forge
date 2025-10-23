<?php

namespace App\Packages\Services\Database\MySQL;

use App\Enums\DatabaseStatus;
use App\Models\Server;
use App\Models\ServerDatabase;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * MySQL Database Installation Job
 *
 * Handles queued MySQL database installation on remote servers with real-time status updates
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
        public Server $server,
        public int $databaseId  // ← Receives database record ID
    ) {}

    public function handle(): void
    {
        // Set no time limit for long-running installation process
        set_time_limit(0);

        // Load the database record from database
        $database = ServerDatabase::findOrFail($this->databaseId);

        Log::info('Starting MySQL database installation', [
            'database_id' => $database->id,
            'server_id' => $this->server->id,
            'version' => $database->version,
        ]);

        try {
            // ✅ UPDATE: pending → installing
            $database->update(['status' => DatabaseStatus::Installing]);
            // Model event broadcasts automatically via Reverb

            // Create installer instance
            $installer = new MySqlInstaller($this->server);

            // Execute installation with database-specific parameters
            $installer->execute($database->version, $database->root_password);

            // ✅ UPDATE: installing → active
            $database->update(['status' => DatabaseStatus::Active]);
            // Model event broadcasts automatically via Reverb

            Log::info('MySQL database installation completed', [
                'database_id' => $database->id,
                'server_id' => $this->server->id,
            ]);

        } catch (Exception $e) {
            // ✅ UPDATE: any → failed
            $database->update([
                'status' => DatabaseStatus::Failed,
                'error_log' => $e->getMessage(),
            ]);
            // Model event broadcasts automatically via Reverb

            Log::error('MySQL database installation failed', [
                'database_id' => $database->id,
                'server_id' => $this->server->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;  // Re-throw for Laravel's retry mechanism
        }
    }

    /**
     * Handle a job failure (timeout, fatal error, worker crash).
     *
     * This is Laravel's safety net for failures that occur outside normal execution flow.
     */
    public function failed(\Throwable $exception): void
    {
        $database = ServerDatabase::find($this->databaseId);

        if ($database) {
            $database->update([
                'status' => DatabaseStatus::Failed,
                'error_log' => $exception->getMessage(),
            ]);
        }

        Log::error('MySQL installation job failed', [
            'database_id' => $this->databaseId,
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
