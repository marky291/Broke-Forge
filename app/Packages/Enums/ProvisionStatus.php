<?php

namespace App\Packages\Enums;

enum ProvisionStatus: string
{
    /**
     * Server is waiting to start provisioning
     */
    case Pending = 'pending';

    /**
     * Initial server access setup in progress
     */
    case Connecting = 'connecting';

    /**
     * Server connected, installing services
     */
    case Installing = 'installing';

    /**
     * All provisioning completed successfully
     */
    case Completed = 'completed';

    /**
     * Provisioning failed at some stage
     */
    case Failed = 'failed';

    /**
     * Get human-readable label for the provision status
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Connecting => 'Setting up access',
            self::Installing => 'Installing services',
            self::Completed => 'Provisioned',
            self::Failed => 'Failed',
        };
    }

    /**
     * Get status labels for milestone fallbacks
     */
    public static function statusLabels(): array
    {
        return [
            'pending' => 'Pending',
            'failed' => 'Provisioning failed',
            'completed' => 'Setup complete',
        ];
    }

    /**
     * Get the color associated with this status
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Connecting => 'amber',
            self::Installing => 'blue',
            self::Completed => 'green',
            self::Failed => 'red',
        };
    }
}
