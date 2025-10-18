<?php

namespace App\Packages\Base;

interface Package
{
    /**
     * Note: SSH user is auto-detected by convention via PackageManager::user():
     * - ServerPackage implementations automatically use 'root'
     * - SitePackage implementations automatically use 'brokeforge'
     * Override user() in PackageManager if custom behavior needed (rare)
     */
}
