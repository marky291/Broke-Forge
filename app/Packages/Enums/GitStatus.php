<?php

namespace App\Packages\Enums;

enum GitStatus: string
{
    case NotInstalled = 'not_installed';
    case Installing = 'installing';
    case Installed = 'installed';
    case Failed = 'failed';
    case Updating = 'updating';

    /**
     * Get human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::NotInstalled => 'Not Installed',
            self::Installing => 'Installing',
            self::Installed => 'Installed',
            self::Failed => 'Failed',
            self::Updating => 'Updating',
        };
    }

    /**
     * Check if the status represents a processing state.
     */
    public function isProcessing(): bool
    {
        return in_array($this, [self::Installing, self::Updating], true);
    }

    /**
     * Check if the status represents a terminal state.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Installed, self::Failed], true);
    }

    /**
     * Check if retry is allowed for this status.
     */
    public function canRetry(): bool
    {
        return in_array($this, [self::Failed, self::NotInstalled], true);
    }
}
