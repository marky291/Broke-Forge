<?php

namespace App\Packages\Services\Database\Redis;

use App\Enums\DatabaseStatus;
use App\Models\Server;
use App\Models\ServerDatabase;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Redis Removal Job
 *
 * Handles queued Redis removal from remote servers with lifecycle management
 */
class RedisRemoverJob implements ShouldQueue
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

        Log::info('Starting Redis removal', [
            'database_id' => $database->id,
            'server_id' => $this->server->id,
            'version' => $database->version,
        ]);

        try {
            // ✅ UPDATE: active → removing
            // Model event broadcasts automatically via Reverb
            $database->update(['status' => 'uninstalling']);

            // Create remover instance
            $remover = new RedisRemover($this->server);

            // Execute removal
            $remover->execute();

            // ✅ DELETE record from database on success
            // Model's deleted() event broadcasts automatically via Reverb
            $database->delete();

            Log::info('Redis removal completed', [
                'database_id' => $this->databaseId,
                'server_id' => $this->server->id,
            ]);

        } catch (Exception $e) {
            // ✅ UPDATE: any → failed
            $database->update([
                'status' => DatabaseStatus::Failed,
                'error_log' => $e->getMessage(),
            ]);
            // Model event broadcasts automatically via Reverb

            Log::error('Redis removal failed', [
                'database_id' => $database->id,
                'server_id' => $this->server->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;  // Re-throw for Laravel's retry mechanism
        }
    }

    public function failed(\Throwable $exception): void
    {
        $database = ServerDatabase::find($this->databaseId);

        if ($database) {
            $database->update([
                'status' => DatabaseStatus::Failed,
                'error_log' => $exception->getMessage(),
            ]);
        }

        Log::error('Redis removal job failed', [
            'database_id' => $this->databaseId,
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
