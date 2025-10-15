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
            $php->update(['status' => PhpStatus::Failed]);
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
}
