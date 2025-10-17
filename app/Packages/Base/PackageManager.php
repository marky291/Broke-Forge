<?php

namespace App\Packages\Base;

use App\Models\Server;
use App\Models\ServerSite;
use Closure;
use Exception;
use Illuminate\Database\Eloquent\Model;
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
     * Get the total steps that have been run
     */
    protected int $milestoneStep = 1;

    /**
     * Track the current event being processed
     */
    protected ?Model $currentEvent = null;

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

    /**
     * Track all events created during this execution
     */
    protected array $allEvents = [];

    /**
     * Get the actionable name based on inherited class
     */
    protected function actionableName(): string
    {
        if ($this instanceof PackageRemover) {
            return 'Removing';
        }

        return 'Installing';
    }

    /**
     * Count the total milestone steps in the installation
     * provision commands
     */
    protected function countMilestones(): int
    {
        return $this->milestones()->countLabels();
    }

    /**
     * Track provision milestone and persist to database
     *
     * Creates a closure that logs and persists the provision event
     * when executed by the SSH command callback system
     */
    protected function track(string $milestone): Closure
    {
        $service = $this->packageType()->value;
        $milestoneStep = $this->milestoneStep++;
        $totalMileSteps = $this->countMilestones();

        return function () use ($milestone, $service, $milestoneStep, $totalMileSteps) {
            // Log the milestone for debugging purposes
            Log::info("{$this->actionableName()} milestone: {$milestone} (step {$milestoneStep}/{$totalMileSteps}) for service {$service}", [
                'server_id' => $this->server->id,
                'service' => $service,
            ]);

            // Mark previous event as success if exists
            if ($this->currentEvent) {
                $this->currentEvent->update(['status' => 'success']);
            }

            // Persist the provision event to database for frontend tracking
            if ($this instanceof \App\Packages\Base\ServerPackage || $this instanceof \App\Packages\Base\SitePackage) {
                $eventData = [
                    'server_id' => $this->server->id,
                    'service_type' => $service,
                    'provision_type' => $this->actionableName() == 'Installing' ? 'install' : 'uninstall',
                    'milestone' => $milestone,
                    'current_step' => $milestoneStep,
                    'total_steps' => $totalMileSteps,
                    'details' => [
                        'server_ip' => $this->server->public_ip,
                        'server_name' => $this->server->vanity_name,
                        'timestamp' => now()->toISOString(),
                    ],
                    'status' => 'pending', // Start as pending
                    'error_log' => null,
                ];

                // Add site_id if this is a site-level package
                if ($this instanceof \App\Packages\Base\SitePackage && $this->site) {
                    $eventData['server_site_id'] = $this->site->id;
                }

                $this->currentEvent = $this->server->events()->create($eventData);
            } else {
                throw new Exception('Unknown package type. Class: '.get_class($this));
            }

            // Track this event
            $this->allEvents[] = $this->currentEvent;
        };
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

                        // Mark current event as failed if exists
                        if ($this->currentEvent) {
                            $this->currentEvent->update([
                                'status' => 'failed',
                                'error_log' => $fullError,
                            ]);
                        }

                        Log::error($fullError, ['user' => $user, 'server' => $this->server]);

                        // Include error output in exception message for better debugging
                        $errorMessage = $errorOutput ? "$command - $errorOutput" : $command;
                        throw new \RuntimeException("Command failed: $errorMessage");
                    }
                }
            }

            // Mark the last event as success after completing all commands successfully
            if ($this->currentEvent) {
                $this->currentEvent->update(['status' => 'success']);
            }
        } catch (ProcessTimedOutException $e) {
            // Handle SSH command timeout specifically
            $timeoutDuration = 570; // Match the setTimeout value above
            $errorMessage = "SSH command timed out after {$timeoutDuration} seconds. This may indicate a slow network, large package downloads, or a command that's hanging. Check the server's network connectivity and system resources.";

            // Log detailed timeout information
            Log::error('SSH command timeout in PackageManager', [
                'server_id' => $this->server->id,
                'service' => $this->packageType()->value,
                'user' => $this->user(),
                'timeout_seconds' => $timeoutDuration,
                'current_milestone' => $this->currentEvent?->milestone,
                'exception' => $e->getMessage(),
            ]);

            // Mark current event as failed
            if ($this->currentEvent) {
                $this->currentEvent->update([
                    'status' => 'failed',
                    'error_log' => $errorMessage."\n\nOriginal error:\n".$e->getMessage()."\n\nStack trace:\n".$e->getTraceAsString(),
                ]);
            }

            // Mark any previous events that are still pending as completed up to the failure point
            foreach ($this->allEvents as $event) {
                if ($this->currentEvent && $event->id !== $this->currentEvent->id && $event->status === 'pending') {
                    $event->update(['status' => 'success']);
                }
            }

            throw new \RuntimeException($errorMessage, 0, $e);
        } catch (\Exception $e) {
            // Handle any other exception
            $errorMessage = $e->getMessage();

            // Check for timeout error patterns as fallback
            if (str_contains($errorMessage, 'Maximum execution time') || str_contains($errorMessage, 'timeout')) {
                $errorMessage = 'Process timeout: '.$errorMessage;
            }

            // Mark current event as failed
            if ($this->currentEvent) {
                $this->currentEvent->update([
                    'status' => 'failed',
                    'error_log' => $errorMessage."\n\nStack trace:\n".$e->getTraceAsString(),
                ]);
            }

            // Mark any previous events that are still pending as completed up to the failure point
            // This ensures we have accurate tracking of which milestones succeeded
            foreach ($this->allEvents as $event) {
                if ($event->id !== $this->currentEvent->id && $event->status === 'pending') {
                    $event->update(['status' => 'success']);
                }
            }

            Log::error('Package manager error: '.$errorMessage, [
                'server' => $this->server->id,
                'service' => $this->packageType()->value,
                'exception' => $e,
            ]);

            throw $e;
        }
    }
}
