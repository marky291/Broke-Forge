<?php

namespace App\Packages\Core\Base;

use App\Models\Server;
use App\Models\ServerSite;
use Closure;
use Exception;
use Illuminate\Support\Facades\Log;
use Spatie\Ssh\Ssh;
use Stringable;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

/**
 * Base class for managing server services (installation and removal)
 */
abstract class PackageManager implements Package
{
    protected Server $server;

    /**
     * Optional site for site-level packages
     */
    protected ?ServerSite $site = null;

    /**
     * Set the site for site-level packages
     */
    public function setSite(ServerSite $site): self
    {
        $this->site = $site;

        return $this;
    }

    /**
     * Get the SSH user for this package's operations.
     *
     * Convention over configuration:
     * - ServerPackage implementations automatically use 'root'
     * - SitePackage implementations automatically use 'brokeforge'
     *
     * Override this method if custom behavior is needed (rare).
     */
    protected function user(): string
    {
        return $this instanceof SitePackage ? 'brokeforge' : 'root';
    }

    protected function sendCommandsToRemote(array $commandList): void
    {
        try {
            foreach ($commandList as $index => $command) {

                // Execute closures, only use SSH if its string return.
                // allowing other logic to bypass ssh command execution.
                if ($command instanceof Closure) {
                    try {
                        $output = $command();
                    } catch (\Throwable $e) {
                        Log::warning('Closure command threw an exception', [
                            'index' => $index ?? null,
                            'exception' => $e->getMessage(),
                        ]);
                        throw new Exception('Closure command threw an exception at index '.($index ?? 'unknown').': '.$e->getMessage());
                    }

                    // Check if the output should be executed as SSH command
                    // Only string outputs should be executed as SSH commands
                    if (is_string($output)) {
                        Log::debug('Found closure-based string command', [
                            'index' => $index ?? null,
                            'output' => $output,
                        ]);

                        // Replace the closure with its string output for SSH execution
                        $command = $output;
                    } elseif ($output instanceof Stringable) {
                        // Convert Stringable to string for SSH execution
                        $output = (string) $output;
                        Log::debug('Found closure-based stringable command', [
                            'index' => $index ?? null,
                            'output' => $output,
                        ]);
                        $command = $output;
                    } else {
                        // Non-string outputs (like Model instances) should not be executed as SSH commands
                        Log::debug('Skipping non-string command output', [
                            'index' => $index ?? null,
                            'type' => get_debug_type($output),
                        ]);

                        continue;
                    }
                }

                // Only execute SSH commands for strings
                if (is_string($command)) {
                    // Get server-specific user for this package's operations
                    $user = $this->user();

                    // Execute SSH commands with timeout to prevent hanging
                    // 570 seconds (9.5 min) allows 30s buffer before 600s job timeout
                    $process = $this->server->ssh($user)
                        ->setTimeout(570)
                        ->execute($command);

                    Log::debug("SSH command: {$process->getCommandLine()}");

                    if (! $process->isSuccessful()) {
                        $errorOutput = trim($process->getErrorOutput());
                        $fullError = "Failed to execute command: $command\nError Output: ".$errorOutput;

                        Log::error($fullError, ['user' => $user, 'server' => $this->server]);

                        // Include error output in exception message for better debugging
                        $errorMessage = $errorOutput ? "$command - $errorOutput" : $command;
                        throw new \RuntimeException("Command failed: $errorMessage");
                    }
                }
            }
        } catch (ProcessTimedOutException $e) {
            // Handle SSH command timeout specifically
            $timeoutDuration = 570; // Match the setTimeout value above
            $errorMessage = "SSH command timed out after {$timeoutDuration} seconds. This may indicate a slow network, large package downloads, or a command that's hanging. Check the server's network connectivity and system resources.";

            // Log detailed timeout information
            Log::error('SSH command timeout in PackageManager', [
                'server_id' => $this->server->id,
                'user' => $this->user(),
                'timeout_seconds' => $timeoutDuration,
                'exception' => $e->getMessage(),
            ]);

            throw new \RuntimeException($errorMessage, 0, $e);
        } catch (\Exception $e) {
            // Handle any other exception
            $errorMessage = $e->getMessage();

            // Check for timeout error patterns as fallback
            if (str_contains($errorMessage, 'Maximum execution time') || str_contains($errorMessage, 'timeout')) {
                $errorMessage = 'Process timeout: '.$errorMessage;
            }

            Log::error('Package manager error: '.$errorMessage, [
                'server' => $this->server->id,
                'exception' => $e,
            ]);

            throw $e;
        }
    }
}
