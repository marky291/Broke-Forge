<?php

namespace App\Packages\Services\PHP;

use App\Enums\PhpStatus;
use App\Models\Server;
use App\Models\ServerPhp;
use App\Packages\Enums\PhpVersion;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * PHP Installation Job
 *
 * Handles queued PHP installation on remote servers with real-time status updates
 */
class PhpInstallerJob implements ShouldQueue
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
        public int $phpId  // ← Receives PHP record ID, NOT version enum
    ) {}

    public function handle(): void
    {
        // Set no time limit for long-running installation process
        set_time_limit(0);

        // Load the PHP record from database
        $php = ServerPhp::findOrFail($this->phpId);

        // Map version string to PhpVersion enum
        $phpVersion = PhpVersion::from($php->version);

        Log::info("Starting PHP {$phpVersion->value} installation", [
            'php_id' => $php->id,
            'server_id' => $this->server->id,
            'version' => $phpVersion->value,
        ]);

        try {
            // ✅ UPDATE: pending → installing
            $php->update(['status' => PhpStatus::Installing]);
            // Model event broadcasts automatically via Reverb

            // Create installer instance
            $installer = new PhpInstaller($this->server);

            // Execute installation
            $installer->execute($phpVersion);

            // ✅ UPDATE: installing → active
            $php->update(['status' => PhpStatus::Active]);
            // Model event broadcasts automatically via Reverb

            Log::info("PHP {$phpVersion->value} installation completed", [
                'php_id' => $php->id,
                'server_id' => $this->server->id,
            ]);

        } catch (Exception $e) {
            // ✅ UPDATE: any → failed
            $php->update([
                'status' => PhpStatus::Failed,
                'error_log' => $e->getMessage(),
            ]);
            // Model event broadcasts automatically via Reverb

            Log::error("PHP {$phpVersion->value} installation failed", [
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

        Log::error('PhpInstallerJob job failed', [
            'php_id' => $this->phpId,
            'server_id' => $this->server->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
