<?php

namespace App\Provision\Sites;

use App\Provision\Milestones;

class DeprovisionSiteMilestones extends Milestones
{
    public const DISABLE_SITE = 'Disabling site';

    public const TEST_CONFIGURATION = 'Testing nginx configuration';

    public const RELOAD_NGINX = 'Reloading nginx';

    public const ARCHIVE_CONFIGURATION = 'Archiving site configuration';

    public const COMPLETE = 'Site deprovisioning complete';

    public function countLabels(): int
    {
        return 5;
    }
}
