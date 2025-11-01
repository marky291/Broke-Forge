<?php

namespace App\Packages\Services\Node;

use App\Enums\TaskStatus;
use App\Packages\Core\Base\PackageInstaller;
use App\Packages\Core\Base\ServerPackage;

/**
 * Composer Update Class
 *
 * Handles updating Composer to the latest version
 */
class ComposerUpdater extends PackageInstaller implements ServerPackage
{
    /**
     * Mark Composer update as failed in database
     */
    protected function markResourceAsFailed(string $errorMessage): void
    {
        $this->server->update([
            'composer_status' => TaskStatus::Failed,
            'composer_error_log' => $errorMessage,
        ]);
    }

    /**
     * Execute Composer update to latest version
     */
    public function execute(): void
    {
        $this->install($this->commands());
    }

    protected function commands(): array
    {
        return [
            // Self-update Composer to latest version
            'composer self-update',

            // Verify Composer version
            'composer --version',
        ];
    }
}
