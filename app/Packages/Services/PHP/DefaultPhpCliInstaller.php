<?php

namespace App\Packages\Services\PHP;

use App\Packages\Core\Base\PackageInstaller;
use App\Packages\Core\Base\ServerPackage;
use App\Packages\Enums\PhpVersion;

/**
 * Default PHP CLI Installer
 *
 * Sets the system-wide PHP CLI default using update-alternatives
 */
class DefaultPhpCliInstaller extends PackageInstaller implements ServerPackage
{
    /**
     * Execute setting the PHP CLI default
     */
    public function execute(PhpVersion $phpVersion): void
    {
        $this->install($this->commands($phpVersion));
    }

    /**
     * Get the commands to set the PHP CLI default
     */
    protected function commands(PhpVersion $phpVersion): array
    {
        return [
            sprintf(
                'update-alternatives --set php /usr/bin/php%s',
                $phpVersion->value
            ),
        ];
    }
}
