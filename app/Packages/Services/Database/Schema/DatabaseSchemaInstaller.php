<?php

namespace App\Packages\Services\Database\Schema;

use App\Models\Server;
use App\Models\ServerDatabase;
use App\Packages\Core\Base\PackageInstaller;

/**
 * Database Schema Installer
 *
 * Executes CREATE DATABASE commands on MySQL/MariaDB/PostgreSQL servers
 */
class DatabaseSchemaInstaller extends PackageInstaller implements \App\Packages\Core\Base\ServerPackage
{
    public function __construct(
        protected Server $server,
        protected ServerDatabase $database
    ) {}

    /**
     * Execute the database schema creation
     */
    public function execute(string $schemaName, string $characterSet = 'utf8mb4', string $collation = 'utf8mb4_unicode_ci'): void
    {
        $this->install($this->commands($schemaName, $characterSet, $collation));
    }

    protected function commands(string $schemaName, string $characterSet, string $collation): array
    {
        $rootPassword = $this->database->root_password;
        $databaseType = $this->database->type->value;

        if (in_array($databaseType, ['mysql', 'mariadb'])) {
            return [
                // Create database with specified character set and collation
                "mysql -u root -p{$rootPassword} -e \"CREATE DATABASE \`{$schemaName}\` CHARACTER SET {$characterSet} COLLATE {$collation};\"",

                // Verify database was created
                "mysql -u root -p{$rootPassword} -e \"SHOW DATABASES LIKE '{$schemaName}';\"",
            ];
        }

        if ($databaseType === 'postgresql') {
            return [
                // Create database with specified encoding
                "sudo -u postgres psql -c \"CREATE DATABASE {$schemaName} ENCODING 'UTF8';\"",

                // Verify database was created
                "sudo -u postgres psql -lqt | cut -d \\| -f 1 | grep -qw {$schemaName}",
            ];
        }

        throw new \RuntimeException("Unsupported database type: {$databaseType}");
    }
}
