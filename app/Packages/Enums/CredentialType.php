<?php

namespace App\Packages\Enums;

/**
 * SSH Credential Type Enum
 *
 * Defines the two types of SSH credentials used for server operations.
 * Each type has a specific username and use case.
 */
enum CredentialType: string
{
    /**
     * Root credential - for server-level operations requiring elevated privileges
     * Used for: System package installation, service management, server configuration
     */
    case Root = 'root';

    /**
     * BrokeForge credential - for site-level operations and Git management
     * Used for: Site deployments, Git operations, application code management
     * Has full permissions only on /home/brokeforge/ directory
     */
    case BrokeForge = 'brokeforge';

    /**
     * Get the SSH username for this credential type.
     *
     * @return string The username to use when connecting via SSH
     */
    public function username(): string
    {
        return match ($this) {
            self::Root => 'root',
            self::BrokeForge => 'brokeforge',
        };
    }

    /**
     * Convert string to CredentialType enum.
     *
     * @param  string  $value  The credential type as string ('root' or 'brokeforge')
     *
     * @throws \ValueError If the value is not a valid credential type
     */
    public static function fromString(string $value): self
    {
        return self::from($value);
    }
}
