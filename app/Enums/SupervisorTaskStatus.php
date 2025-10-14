<?php

namespace App\Enums;

/**
 * Supervisor Task Status Enum
 *
 * Represents the lifecycle states of a supervisor task
 */
enum SupervisorTaskStatus: string
{
    case Pending = 'pending';       // Record created, job not started
    case Installing = 'installing';  // Job actively running
    case Active = 'active';         // Installation completed successfully, task running
    case Inactive = 'inactive';     // Task manually stopped
    case Failed = 'failed';         // Installation failed with errors
    case Removing = 'removing';     // Removal in progress
}
