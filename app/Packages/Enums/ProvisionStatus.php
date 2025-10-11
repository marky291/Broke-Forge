<?php

namespace App\Packages\Enums;

enum ProvisionStatus: string
{
    /**
     * Server is waiting to start provisioning
     */
    case Pending = 'pending';

    /**
     * Server connected, installing services
     */
    case Installing = 'installing';

    /**
     * All provisioning completed successfully
     */
    case Completed = 'completed';

    /**
     * Provisioning failed at some stage
     */
    case Failed = 'failed';
}
