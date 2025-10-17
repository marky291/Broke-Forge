<?php

namespace App\Packages\Services\Sites\Command;

use App\Models\Server;
use App\Models\ServerSite;
use App\Models\ServerSiteCommandHistory;
use App\Packages\Base\Milestones;
use App\Packages\Base\PackageInstaller;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

/**
 * Site Command Installer
 *
 * Executes custom commands within site directories following package patterns
 */
class SiteCommandInstaller extends PackageInstaller implements \App\Packages\Base\ServerPackage
{
    protected ?ServerSite $site;

    protected ?string $commandOutput = null;

    protected ?string $commandError = null;

    public function __construct(Server $server, ServerSite $site)
    {
        parent::__construct($server);
        $this->site = $site;
    }

    /**
     * Execute a custom command within the site directory
     *
     * @return array{command: string, output: string, errorOutput: string, exitCode: int|null, ranAt: string, durationMs: int, success: bool}
     */
    public function execute(string $command, int $timeout = 120): array
    {
        $command = trim($command);

        if ($command === '') {
            throw new RuntimeException('Cannot execute an empty command.');
        }

        $start = (int) (microtime(true) * 1000);

        try {
            $this->install($this->commands($command, $timeout));
            $duration = (int) (microtime(true) * 1000) - $start;

            $result = [
                'command' => $command,
                'output' => $this->commandOutput ?? '',
                'errorOutput' => $this->commandError ?? '',
                'exitCode' => 0,
                'ranAt' => now()->toIso8601String(),
                'durationMs' => $duration,
                'success' => true,
            ];

            $this->saveToHistory($result);

            return $result;
        } catch (ProcessTimedOutException $exception) {
            $duration = (int) (microtime(true) * 1000) - $start;

            $result = [
                'command' => $command,
                'output' => '',
                'errorOutput' => 'Command timed out after 120 seconds.',
                'exitCode' => $exception->getProcess()?->getExitCode(),
                'ranAt' => now()->toIso8601String(),
                'durationMs' => $duration,
                'success' => false,
            ];

            $this->saveToHistory($result);

            return $result;
        } catch (\Exception $e) {
            $duration = (int) (microtime(true) * 1000) - $start;

            $result = [
                'command' => $command,
                'output' => '',
                'errorOutput' => $e->getMessage(),
                'exitCode' => 1,
                'ranAt' => now()->toIso8601String(),
                'durationMs' => $duration,
                'success' => false,
            ];

            $this->saveToHistory($result);

            return $result;
        }
    }

    /**
     * Generate SSH commands for command execution
     */
    protected function commands(string $command, int $timeout): array
    {
        // Get app user for working directory resolution
        $brokeforgeCredential = $this->server->credentials()
            ->where('user', 'brokeforge')
            ->first();

        $appUser = $brokeforgeCredential?->getUsername()
            ?: str_replace(' ', '', strtolower(config('app.name')));

        // Resolve working directory to site root (parent of document_root)
        // e.g., /home/brokeforge/example.com instead of /home/brokeforge/example.com/public
        $workingDirectory = $this->site->document_root
            ? dirname($this->site->document_root)
            : ($this->site->domain
                ? "/home/{$appUser}/{$this->site->domain}"
                : "/home/{$appUser}/site-{$this->site->id}");

        return [
            $this->track(SiteCommandInstallerMilestones::PREPARE_EXECUTION),

            // Capture command output for return
            function () use ($command, $workingDirectory, $timeout) {
                $remoteCommand = sprintf('cd %s && %s', escapeshellarg($workingDirectory), $command);

                $process = $this->server->ssh('brokeforge')
                    ->setTimeout($timeout)
                    ->execute($remoteCommand);

                // Store output for execute method to return
                $this->commandOutput = rtrim($process->getOutput());
                $this->commandError = rtrim($process->getErrorOutput());

                if (! $process->isSuccessful()) {
                    Log::warning('Site command exited with a non-zero code.', [
                        'server_id' => $this->server->id,
                        'site_id' => $this->site->id,
                        'command' => $command,
                        'exit_code' => $process->getExitCode(),
                        'stderr' => $this->commandError,
                    ]);

                    throw new RuntimeException("Command failed with exit code {$process->getExitCode()}");
                }
            },

            $this->track(SiteCommandInstallerMilestones::COMMAND_COMPLETE),
        ];
    }

    public function packageName(): PackageName
    {
        return PackageName::Command;
    }

    public function packageType(): PackageType
    {
        return PackageType::Command;
    }

    public function milestones(): Milestones
    {
        return new SiteCommandInstallerMilestones;
    }

    /**
     * Save command result to history
     */
    protected function saveToHistory(array $result): void
    {
        ServerSiteCommandHistory::create([
            'server_id' => $this->server->id,
            'server_site_id' => $this->site->id,
            'command' => $result['command'],
            'output' => $result['output'],
            'error_output' => $result['errorOutput'],
            'exit_code' => $result['exitCode'],
            'duration_ms' => $result['durationMs'],
            'success' => $result['success'],
        ]);
    }
}
