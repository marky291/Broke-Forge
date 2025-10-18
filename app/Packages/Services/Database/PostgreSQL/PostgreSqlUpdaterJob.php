<?php

namespace App\Packages\Services\Database\PostgreSQL;

use App\Enums\DatabaseStatus;
use App\Models\Server;
use App\Models\ServerDatabase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PostgreSqlUpdaterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        // Set no time limit for long-running update process
        set_time_limit(0);

        // Load the database record from database
        $database = ServerDatabase::findOrFail($this->databaseId);
        $targetVersion = $database->version;  // Get version from database record

        Log::info('Starting PostgreSQL database update', [
            'database_id' => $database->id,
            'server_id' => $this->server->id,
            'target_version' => $targetVersion,
        ]);

        try {
            // Create updater instance
            $updater = new PostgreSqlUpdater($this->server);

            // Execute update
            $updater->execute($targetVersion);

            // ✅ UPDATE: updating → active
            $database->update(['status' => DatabaseStatus::Active]);
            // Model event broadcasts automatically via Reverb

            Log::info('PostgreSQL database update completed', [
                'database_id' => $database->id,
                'server_id' => $this->server->id,
            ]);

        } catch (\Exception $e) {
            // ✅ UPDATE: any → failed
            $database->update([
                'status' => DatabaseStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);
            // Model event broadcasts automatically via Reverb

            Log::error('PostgreSQL database update failed', [
                'database_id' => $database->id,
                'server_id' => $this->server->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;  // Re-throw for Laravel's retry mechanism
        }
    }
}
