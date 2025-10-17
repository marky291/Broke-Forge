<?php

namespace App\Packages\Base;

use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;

interface Package
{
    /**
     * Generic name of the current package.
     */
    public function packageName(): PackageName;

    /**
     * Package categorization type such as database, cache, queue etc.
     */
    public function packageType(): PackageType;

    /**
     * Milestones to track package progression.
     */
    public function milestones(): Milestones;

    /**
     * Note: SSH user is auto-detected by convention via PackageManager::user():
     * - ServerPackage implementations automatically use 'root'
     * - SitePackage implementations automatically use 'brokeforge'
     * Override user() in PackageManager if custom behavior needed (rare)
     */
}
