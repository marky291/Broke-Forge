<?php

namespace App\Enums;

/**
 * Deployment Status Enum
 *
 * Represents the lifecycle states of a site deployment
 */
enum DeploymentStatus: string
{
    case Pending = 'pending';       // Record created, job not started
    case Running = 'running';        // Job actively running
    case Success = 'success';        // Deployment completed successfully
    case Failed = 'failed';          // Deployment failed with errors
}
