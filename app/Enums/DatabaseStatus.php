<?php

namespace App\Enums;

/**
 * Database Installation Status Enum
 *
 * Represents the lifecycle states of a database installation
 */
enum DatabaseStatus: string
{
    case Pending = 'pending';         // Record created, job not started
    case Installing = 'installing';    // Installation job actively running
    case Active = 'active';           // Installation completed successfully, database running
    case Failed = 'failed';           // Installation/operation failed with errors
    case Stopped = 'stopped';         // Database stopped/disabled
    case Uninstalling = 'uninstalling'; // Uninstallation in progress
    case Updating = 'updating';       // Update/upgrade in progress
}
