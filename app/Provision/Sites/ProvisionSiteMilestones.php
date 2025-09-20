<?php

namespace App\Provision\Sites;

use App\Provision\Milestones;

class ProvisionSiteMilestones extends Milestones
{
    public const PREPARE_DIRECTORIES = 'Preparing site directories';

    public const CREATE_CONFIG = 'Creating nginx configuration';

    public const ENABLE_SITE = 'Enabling site';

    public const TEST_CONFIG = 'Testing nginx configuration';

    public const RELOAD_NGINX = 'Reloading nginx';

    public const SET_PERMISSIONS = 'Setting file permissions';

    public const COMPLETE = 'Site setup complete';

    public function countLabels(): int
    {
        return 7;
    }
}
