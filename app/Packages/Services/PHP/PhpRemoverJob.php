<?php

namespace App\Packages\Services\PHP;

use App\Enums\PhpStatus;
use App\Models\Server;
use App\Models\ServerPhp;
use App\Packages\Enums\PhpVersion;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

/**
 * PHP Removal Job
 *
 * Handles queued PHP removal on remote servers with lifecycle management
 */
class PhpRemoverJob implements ShouldQueue
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
    public $tries = 0;

    /**
     * The number of exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    public function __construct(
        public Server $server,
        public int $phpId  // ← Receives PHP record ID only
    ) {}

    public function handle(): void
    {
        // Set no time limit for long-running removal process
        set_time_limit(0);

        // Load the PHP record from database
        $php = ServerPhp::findOrFail($this->phpId);

        // Map version string to PhpVersion enum
        $phpVersion = PhpVersion::from($php->version);

        Log::info("Starting PHP {$phpVersion->value} removal", [
            'php_id' => $php->id,
            'server_id' => $this->server->id,
            'version' => $phpVersion->value,
        ]);

        try {
            // ✅ UPDATE: active → removing
            // Model event broadcasts automatically via Reverb
            $php->update(['status' => 'removing']);

            // Create remover instance
            $remover = new PhpRemover($this->server);

            // Execute removal
            $remover->execute($phpVersion, $this->phpId);

            // ✅ DELETE record from database on success
            // Model's deleted() event broadcasts automatically via Reverb
            $php->delete();

            Log::info("PHP {$phpVersion->value} removal completed", [
                'php_id' => $this->phpId,
                'server_id' => $this->server->id,
            ]);

        } catch (Exception $e) {
            // ✅ UPDATE: any → failed
            // Model event broadcasts automatically via Reverb
            $php->update([
                'status' => PhpStatus::Failed,
                'error_log' => $e->getMessage(),
            ]);

            Log::error("PHP {$phpVersion->value} removal failed", [
                'php_id' => $php->id,
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
        $php = ServerPhp::find($this->phpId);

        if ($php) {
            $php->update([
                'status' => PhpStatus::Failed,
                'error_log' => $exception->getMessage(),
            ]);
        }

        Log::error('PhpRemoverJob job failed', [
            'php_id' => $this->phpId,
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
