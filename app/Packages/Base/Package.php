<?php

namespace App\Packages\Base;

use App\Packages\Credentials\SshCredential;
use App\Packages\Enums\PackageName;
use App\Packages\Enums\PackageType;

interface Package
{
    /**
     * Generic name of the current package.
     *
     * @return string
     */
    public function packageName(): PackageName;

    /**
     * Package categorization type such as database, cache, queue etc.
     *
     * @return string
     */
    public function packageType(): PackageType;

    /**
     * Milestones to track package progression.
     *
     * @return Milestones
     */
    public function milestones(): Milestones;

    /**
     * Credentials used to run the package on SSH
     *
     * @return SshCredential
     */
    public function sshCredential(): SshCredential;
}
