<?php

namespace App\Packages\Services\Database\Redis;

use App\Enums\DatabaseStatus;
use App\Models\Server;
use App\Models\ServerDatabase;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Redis Installation Job
 *
 * Handles queued Redis installation on remote servers with real-time status updates
 */
class RedisInstallerJob implements ShouldQueue
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
        public int $databaseId  // ← Receives database record ID
    ) {}

    public function handle(): void
    {
        // Set no time limit for long-running installation process
        set_time_limit(0);

        // Load the database record from database
        $database = ServerDatabase::findOrFail($this->databaseId);

        Log::info('Starting Redis installation', [
            'database_id' => $database->id,
            'server_id' => $this->server->id,
            'version' => $database->version,
        ]);

        try {
            // ✅ UPDATE: pending → installing
            $database->update(['status' => DatabaseStatus::Installing]);
            // Model event broadcasts automatically via Reverb

            // Create installer instance
            $installer = new RedisInstaller($this->server);

            // Execute installation with database-specific parameters
            $installer->execute($database->version, $database->port, $database->root_password);

            // ✅ UPDATE: installing → active
            $database->update(['status' => DatabaseStatus::Active]);
            // Model event broadcasts automatically via Reverb

            Log::info('Redis installation completed', [
                'database_id' => $database->id,
                'server_id' => $this->server->id,
            ]);

        } catch (Exception $e) {
            // ✅ UPDATE: any → failed
            $database->update(['status' => DatabaseStatus::Failed, 'error_log' => $e->getMessage()]);
            // Model event broadcasts automatically via Reverb

            Log::error('Redis installation failed', [
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

    public function failed(\Throwable $exception): void
    {
        $database = ServerDatabase::find($this->databaseId);

        if ($database) {
            $database->update([
                'status' => DatabaseStatus::Failed,
                'error_log' => $exception->getMessage(),
            ]);
        }

        Log::error('Redis installation job failed', [
            'database_id' => $this->databaseId,
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
