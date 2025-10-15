<?php

namespace App\Enums;

/**
 * Firewall Rule Status Enum
 *
 * Represents the lifecycle states of a firewall rule installation
 */
enum FirewallRuleStatus: string
{
    case Pending = 'pending';       // Record created, job not started
    case Installing = 'installing';  // Job actively running
    case Active = 'active';         // Installation completed successfully
    case Failed = 'failed';         // Installation failed with errors
    case Removing = 'removing';     // Removal in progress
}
