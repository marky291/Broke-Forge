<?php

namespace App\Enums;

/**
 * Generic Task Status Enum
 *
 * Consolidated status enum used across all packages and services.
 * Represents the lifecycle states for installations, deployments, connections, and other tasks.
 *
 * ## Common Usage Patterns
 *
 * **Lifecycle Operations** (firewall rules, scheduled tasks, supervisor tasks, databases):
 * - Pending → Installing → Active/Failed
 *
 * **One-Time Operations** (deployments, site commands):
 * - Pending → Updating → Success/Failed
 *
 * **Service States** (monitoring, scheduler, supervisor):
 * - Active (running), Paused (disabled), Failed (errored)
 *
 * **Connections** (server connection_status):
 * - Pending (initial), Success (connected), Failed (connection error)
 *
 * **Removals** (uninstalling services/resources):
 * - Pending (Initial) → Removing → deleted (or restore to previous status on failure)
 */
enum TaskStatus: string
{
    /**
     * Waiting to start - Initial state before processing begins.
     *
     * Used for: Newly created resources, queued jobs, initial connection attempts.
     * Examples: Firewall rules awaiting installation, scheduled tasks pending creation.
     */
    case Pending = 'pending';

    /**
     * Installation/setup in progress - Active processing for new resources.
     *
     * Used for: Installing databases, creating firewall rules, setting up scheduled tasks.
     * Examples: MySQL being installed, supervisor task configuration being written.
     */
    case Installing = 'installing';

    /**
     * Running and operational - Service is active and functioning normally.
     *
     * Used for: Long-running services and background processes.
     * Examples: Monitoring service collecting metrics, scheduler running tasks, supervisor managing processes.
     */
    case Active = 'active';

    /**
     * Completed successfully - One-time operation finished without errors.
     *
     * Used for: Completed deployments, established connections, finished one-off tasks.
     * Examples: Server connection established, deployment completed, provision succeeded.
     */
    case Success = 'success';

    /**
     * Failed or errored - Operation encountered an error and cannot proceed.
     *
     * Used for: Installation failures, connection errors, deployment failures.
     * Examples: Database installation failed, firewall rule creation error, connection timeout.
     */
    case Failed = 'failed';

    /**
     * Update/modification in progress - Existing resource being changed.
     *
     * Used for: Deployments pulling latest code, configuration updates.
     * Examples: Site deployment updating from Git, database credentials being updated.
     */
    case Updating = 'updating';

    /**
     * Removal/uninstallation in progress - Resource being deleted or uninstalled.
     *
     * Used for: Uninstalling services, removing firewall rules, deleting scheduled tasks.
     * Examples: Database being uninstalled, monitoring service being removed.
     */
    case Removing = 'removing';

    /**
     * Disabled or stopped - Service exists but is not currently running.
     *
     * Used for: Temporarily disabled services that can be reactivated.
     * Examples: Paused scheduled task, disabled supervisor process.
     */
    case Paused = 'paused';
}
