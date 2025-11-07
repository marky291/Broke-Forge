<?php

namespace App\Packages\Services\Sites;

use App\Models\ServerSite;
use App\Packages\Core\Base\PackageInstaller;

/**
 * Installer for unsetting the default site.
 * Removes the /home/brokeforge/default symlink entirely.
 */
class SiteUnsetDefaultInstaller extends PackageInstaller
{
    /**
     * Execute the default site unset operation.
     */
    public function execute(ServerSite $site): void
    {
        $this->install($this->commands($site));
    }

    protected function commands(ServerSite $site): array
    {
        // Get app user
        $appUser = config('app.ssh_user', str_replace(' ', '', strtolower(config('app.name'))));

        return [
            // Remove the default symlink entirely
            "rm -f /home/{$appUser}/default",

            // Reload PHP-FPM to apply changes
            "sudo service php{$site->php_version}-fpm reload",

            // Verify symlink was removed (should return exit code 1 since file doesn't exist)
            "test ! -e /home/{$appUser}/default",
        ];
    }
}
