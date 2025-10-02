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
     * Credential type used to run the package on SSH.
     *
     * Returns the credential type ('root', 'user', 'worker') which maps
     * to ServerCredential records with unique per-server SSH keys.
     *
     * @return string 'root' for server-level operations, 'user' for site operations, 'worker' for Git
     */
    public function credentialType(): string;
}
