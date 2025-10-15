<?php

namespace App\Packages\Services\Database\MySQL;

use App\Models\Server;
use App\Models\ServerDatabase;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * MySQL Database Removal Job
 *
 * Handles queued MySQL database removal from remote servers with lifecycle management
 */
class MySqlRemoverJob implements ShouldQueue
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
        public int $databaseId  // ← Receives database record ID only
    ) {}

    public function handle(): void
    {
        // Set no time limit for long-running removal process
        set_time_limit(0);

        // Load the database record from database
        $database = ServerDatabase::findOrFail($this->databaseId);
        $originalStatus = $database->status; // Store for rollback on failure

        Log::info('Starting MySQL database removal', [
            'database_id' => $database->id,
            'server_id' => $this->server->id,
            'version' => $database->version,
        ]);

        try {
            // ✅ UPDATE: active → removing
            // Model event broadcasts automatically via Reverb
            $database->update(['status' => 'uninstalling']);

            // Create remover instance
            $remover = new MySqlRemover($this->server);

            // Execute removal
            $remover->execute();

            // ✅ DELETE record from database on success
            // Model's deleted() event broadcasts automatically via Reverb
            $database->delete();

            Log::info('MySQL database removal completed', [
                'database_id' => $this->databaseId,
                'server_id' => $this->server->id,
            ]);

        } catch (Exception $e) {
            // ✅ ROLLBACK: Restore original status on failure (allows retry)
            // Model event broadcasts automatically via Reverb
            $database->update(['status' => $originalStatus]);

            Log::error('MySQL database removal failed', [
                'database_id' => $database->id,
                'server_id' => $this->server->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;  // Re-throw for Laravel's retry mechanism
        }
    }
}
