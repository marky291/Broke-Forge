<?php

namespace App\Packages\Services\Database\Schema;

use App\Models\Server;
use App\Models\ServerDatabase;
use App\Packages\Core\Base\PackageInstaller;

/**
 * Database Schema Remover
 *
 * Executes DROP DATABASE commands on MySQL/MariaDB/PostgreSQL servers
 */
class DatabaseSchemaRemover extends PackageInstaller implements \App\Packages\Core\Base\ServerPackage
{
    public function __construct(
        protected Server $server,
        protected ServerDatabase $database
    ) {}

    /**
     * Execute the database schema deletion
     */
    public function execute(string $schemaName): void
    {
        $this->install($this->commands($schemaName));
    }

    protected function commands(string $schemaName): array
    {
        $rootPassword = $this->database->root_password;
        $databaseType = $this->database->type->value;

        if (in_array($databaseType, ['mysql', 'mariadb'])) {
            return [
                // Drop database
                "mysql -u root -p{$rootPassword} -e \"DROP DATABASE IF EXISTS \`{$schemaName}\`;\"",

                // Verify database was dropped
                "! mysql -u root -p{$rootPassword} -e \"SHOW DATABASES LIKE '{$schemaName}';\" | grep -q {$schemaName}",
            ];
        }

        if ($databaseType === 'postgresql') {
            return [
                // Drop database
                "sudo -u postgres psql -c \"DROP DATABASE IF EXISTS {$schemaName};\"",

                // Verify database was dropped
                "! sudo -u postgres psql -lqt | cut -d \\| -f 1 | grep -qw {$schemaName}",
            ];
        }

        throw new \RuntimeException("Unsupported database type: {$databaseType}");
    }
}
