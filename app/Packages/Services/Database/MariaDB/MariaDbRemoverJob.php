<?php

namespace App\Packages\Services\Database\MariaDB;

use App\Enums\DatabaseStatus;
use App\Models\Server;
use App\Models\ServerDatabase;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * MariaDB Removal Job
 *
 * Handles queued MariaDB removal from remote servers with lifecycle management
 */
class MariaDbRemoverJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

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

        Log::info('Starting MariaDB removal', [
            'database_id' => $database->id,
            'server_id' => $this->server->id,
            'version' => $database->version,
        ]);

        try {
            // ✅ UPDATE: active → uninstalling
            // Model event broadcasts automatically via Reverb
            $database->update(['status' => 'uninstalling']);

            // Create remover instance
            $remover = new MariaDbRemover($this->server);

            // Execute removal
            $remover->execute();

            // ✅ DELETE record from database on success
            // Model's deleted() event broadcasts automatically via Reverb
            $database->delete();

            Log::info('MariaDB removal completed', [
                'database_id' => $this->databaseId,
                'server_id' => $this->server->id,
            ]);

        } catch (Exception $e) {
            // ✅ UPDATE: any → failed
            // Model event broadcasts automatically via Reverb
            $database->update([
                'status' => DatabaseStatus::Failed,
                'error_log' => $e->getMessage(),
            ]);

            Log::error('MariaDB removal failed', [
                'database_id' => $database->id,
                'server_id' => $this->server->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;  // Re-throw for Laravel's retry mechanism
        }
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

        Log::error('MariaDB removal job failed', [
            'database_id' => $this->databaseId,
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
