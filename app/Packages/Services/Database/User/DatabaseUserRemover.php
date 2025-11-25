<?php

namespace App\Packages\Services\Database\User;

use App\Models\Server;
use App\Models\ServerDatabase;
use App\Packages\Core\Base\PackageInstaller;

/**
 * Database User Remover
 *
 * Executes DROP USER commands on MySQL/MariaDB/PostgreSQL servers
 */
class DatabaseUserRemover extends PackageInstaller implements \App\Packages\Core\Base\ServerPackage
{
    public function __construct(
        protected Server $server,
        protected ServerDatabase $database
    ) {}

    /**
     * Execute the database user deletion
     */
    public function execute(string $username, string $host): void
    {
        $this->install($this->commands($username, $host));
    }

    protected function commands(string $username, string $host): array
    {
        $rootPassword = $this->database->root_password;
        $databaseType = $this->database->type->value;

        if (in_array($databaseType, ['mysql', 'mariadb'])) {
            return [
                // Drop user
                "mysql -u root -p{$rootPassword} -e \"DROP USER IF EXISTS '{$username}'@'{$host}';\"",

                // Flush privileges
                "mysql -u root -p{$rootPassword} -e \"FLUSH PRIVILEGES;\"",

                // Verify user was dropped
                "! mysql -u root -p{$rootPassword} -e \"SELECT User FROM mysql.user WHERE User='{$username}' AND Host='{$host}';\" | grep -q {$username}",
            ];
        }

        if ($databaseType === 'postgresql') {
            return [
                // Drop user
                "sudo -u postgres psql -c \"DROP USER IF EXISTS {$username};\"",

                // Verify user was dropped
                "! sudo -u postgres psql -c \"SELECT usename FROM pg_user WHERE usename='{$username}';\" | grep -q {$username}",
            ];
        }

        throw new \RuntimeException("Unsupported database type: {$databaseType}");
    }
}
