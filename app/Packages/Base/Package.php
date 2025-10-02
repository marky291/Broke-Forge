<?php

namespace App\Packages\Base;

use App\Packages\Enums\CredentialType;
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
     * Returns the credential type (Root or BrokeForge) which maps
     * to ServerCredential records with unique per-server SSH keys.
     *
     * @return CredentialType Root for server-level operations, BrokeForge for site operations and Git
     */
    public function credentialType(): CredentialType;
}
