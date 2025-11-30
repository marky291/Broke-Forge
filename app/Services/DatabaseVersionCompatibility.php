<?php

namespace App\Services;

use App\Enums\DatabaseEngine;

class DatabaseVersionCompatibility
{
    /**
     * MariaDB version compatibility matrix
     * Maps MariaDB version to minimum Ubuntu version codename and fallback strategy
     */
    private const MARIADB_COMPATIBILITY = [
        '11.6' => ['min_ubuntu' => 'jammy', 'supports' => ['jammy', 'noble']],
        '11.5' => ['min_ubuntu' => 'jammy', 'supports' => ['jammy', 'noble']],
        '11.4' => ['min_ubuntu' => 'jammy', 'supports' => ['jammy', 'noble']],
        '11.3' => ['min_ubuntu' => 'jammy', 'supports' => ['jammy', 'noble']],
        '11.2' => ['min_ubuntu' => 'jammy', 'supports' => ['jammy', 'noble']],
        '11.1' => ['min_ubuntu' => 'jammy', 'supports' => ['jammy', 'noble']],
        '11.0' => ['min_ubuntu' => 'jammy', 'supports' => ['jammy', 'noble']],
        '10.11' => ['min_ubuntu' => 'jammy', 'supports' => ['jammy', 'noble'], 'fallback' => 'jammy'],
        '10.6' => ['min_ubuntu' => 'focal', 'supports' => ['focal', 'jammy'], 'fallback' => 'jammy'],
        '10.5' => ['min_ubuntu' => 'focal', 'supports' => ['focal', 'jammy'], 'fallback' => 'jammy'],
        '10.4' => ['min_ubuntu' => 'bionic', 'supports' => ['bionic', 'focal', 'jammy'], 'fallback' => 'jammy'],
        '10.3' => ['min_ubuntu' => 'bionic', 'supports' => ['bionic', 'focal'], 'fallback' => 'focal'],
    ];

    /**
     * PostgreSQL version compatibility
     * PostgreSQL official repository supports all Ubuntu LTS versions
     */
    private const POSTGRESQL_COMPATIBILITY = [
        '17' => ['min_ubuntu' => 'jammy', 'supports' => ['jammy', 'noble']],
        '16' => ['min_ubuntu' => 'focal', 'supports' => ['focal', 'jammy', 'noble']],
        '15' => ['min_ubuntu' => 'focal', 'supports' => ['focal', 'jammy', 'noble']],
        '14' => ['min_ubuntu' => 'focal', 'supports' => ['focal', 'jammy', 'noble']],
        '13' => ['min_ubuntu' => 'bionic', 'supports' => ['bionic', 'focal', 'jammy', 'noble']],
        '12' => ['min_ubuntu' => 'bionic', 'supports' => ['bionic', 'focal', 'jammy']],
    ];

    /**
     * MySQL version compatibility
     * MySQL from Ubuntu repos - availability depends on Ubuntu version
     */
    private const MYSQL_COMPATIBILITY = [
        '8.0' => ['min_ubuntu' => 'focal', 'supports' => ['focal', 'jammy', 'noble']],
        '5.7' => ['min_ubuntu' => 'bionic', 'supports' => ['bionic', 'focal']],
    ];

    /**
     * Ubuntu codename to version mapping
     */
    private const UBUNTU_VERSIONS = [
        'noble' => '24.04',
        'jammy' => '22.04',
        'focal' => '20.04',
        'bionic' => '18.04',
    ];

    /**
     * Check if a database version is compatible with an Ubuntu codename
     */
    public function isCompatible(DatabaseEngine $dbType, string $dbVersion, string $ubuntuCodename): bool
    {
        $compatibility = $this->getCompatibilityMatrix($dbType);

        if (! isset($compatibility[$dbVersion])) {
            return false;
        }

        return in_array($ubuntuCodename, $compatibility[$dbVersion]['supports'] ?? []);
    }

    /**
     * Get the appropriate Ubuntu codename to use for a database version
     * Returns the actual codename if supported, or a fallback if configured
     */
    public function getUbuntuCodenameForDatabase(DatabaseEngine $dbType, string $dbVersion, string $serverCodename): ?string
    {
        $compatibility = $this->getCompatibilityMatrix($dbType);

        if (! isset($compatibility[$dbVersion])) {
            return null;
        }

        $versionInfo = $compatibility[$dbVersion];

        // If server codename is directly supported, use it
        if (in_array($serverCodename, $versionInfo['supports'] ?? [])) {
            return $serverCodename;
        }

        // Otherwise, use the fallback if available
        return $versionInfo['fallback'] ?? null;
    }

    /**
     * Get all compatible versions for a database type and Ubuntu codename
     */
    public function getCompatibleVersions(DatabaseEngine $dbType, string $ubuntuCodename): array
    {
        $compatibility = $this->getCompatibilityMatrix($dbType);
        $compatibleVersions = [];

        foreach ($compatibility as $version => $info) {
            // Include if directly supported or has a fallback
            if (in_array($ubuntuCodename, $info['supports'] ?? []) || isset($info['fallback'])) {
                $compatibleVersions[] = $version;
            }
        }

        return $compatibleVersions;
    }

    /**
     * Get the compatibility matrix for a database type
     */
    private function getCompatibilityMatrix(DatabaseEngine $dbType): array
    {
        return match ($dbType) {
            DatabaseEngine::MariaDB => self::MARIADB_COMPATIBILITY,
            DatabaseEngine::PostgreSQL => self::POSTGRESQL_COMPATIBILITY,
            DatabaseEngine::MySQL => self::MYSQL_COMPATIBILITY,
            default => [],
        };
    }

    /**
     * Get Ubuntu version from codename
     */
    public function getUbuntuVersion(string $codename): ?string
    {
        return self::UBUNTU_VERSIONS[$codename] ?? null;
    }

    /**
     * Validate if installation/update is possible
     */
    public function validateInstallation(DatabaseEngine $dbType, string $dbVersion, string $ubuntuCodename): array
    {
        $codename = $this->getUbuntuCodenameForDatabase($dbType, $dbVersion, $ubuntuCodename);

        if ($codename === null) {
            return [
                'valid' => false,
                'error' => "Database {$dbType->value} version {$dbVersion} is not compatible with Ubuntu {$ubuntuCodename}. ".
                          'Compatible versions: '.implode(', ', $this->getCompatibleVersions($dbType, $ubuntuCodename)),
            ];
        }

        $usingFallback = $codename !== $ubuntuCodename;

        return [
            'valid' => true,
            'codename' => $codename,
            'using_fallback' => $usingFallback,
            'message' => $usingFallback
                ? "Using Ubuntu {$codename} packages for compatibility with {$dbType->value} {$dbVersion}"
                : null,
        ];
    }
}
