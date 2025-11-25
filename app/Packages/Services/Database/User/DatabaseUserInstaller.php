<?php

namespace App\Packages\Services\Database\User;

use App\Models\Server;
use App\Models\ServerDatabase;
use App\Packages\Core\Base\PackageInstaller;

/**
 * Database User Installer
 *
 * Executes CREATE USER and GRANT commands on MySQL/MariaDB/PostgreSQL servers
 */
class DatabaseUserInstaller extends PackageInstaller implements \App\Packages\Core\Base\ServerPackage
{
    public function __construct(
        protected Server $server,
        protected ServerDatabase $database
    ) {}

    /**
     * Execute the database user creation
     */
    public function execute(string $username, string $password, string $host, string $privileges, array $schemas): void
    {
        $this->install($this->commands($username, $password, $host, $privileges, $schemas));
    }

    protected function commands(string $username, string $password, string $host, string $privileges, array $schemas): array
    {
        $rootPassword = $this->database->root_password;
        $databaseType = $this->database->type->value;

        if (in_array($databaseType, ['mysql', 'mariadb'])) {
            return $this->mysqlCommands($username, $password, $host, $privileges, $schemas, $rootPassword);
        }

        if ($databaseType === 'postgresql') {
            return $this->postgresCommands($username, $password, $privileges, $schemas);
        }

        throw new \RuntimeException("Unsupported database type: {$databaseType}");
    }

    protected function mysqlCommands(string $username, string $password, string $host, string $privileges, array $schemas, string $rootPassword): array
    {
        $commands = [];

        // Create user (IF NOT EXISTS prevents errors if user already exists from failed attempts)
        $commands[] = "mysql -u root -p{$rootPassword} -e \"CREATE USER IF NOT EXISTS '{$username}'@'{$host}' IDENTIFIED BY '{$password}';\"";

        // Grant privileges based on type
        $grantPrivileges = $this->getGrantPrivileges($privileges);

        // Grant to each schema
        foreach ($schemas as $schema) {
            $commands[] = "mysql -u root -p{$rootPassword} -e \"GRANT {$grantPrivileges} ON \`{$schema}\`.* TO '{$username}'@'{$host}';\"";
        }

        // Flush privileges
        $commands[] = "mysql -u root -p{$rootPassword} -e \"FLUSH PRIVILEGES;\"";

        // Verify user was created
        $commands[] = "mysql -u root -p{$rootPassword} -e \"SELECT User, Host FROM mysql.user WHERE User='{$username}' AND Host='{$host}';\"";

        return $commands;
    }

    protected function postgresCommands(string $username, string $password, string $privileges, array $schemas): array
    {
        $commands = [];

        // Create user (CREATE ROLE IF NOT EXISTS prevents errors if user already exists from failed attempts)
        $commands[] = "sudo -u postgres psql -c \"CREATE ROLE IF NOT EXISTS {$username} WITH LOGIN PASSWORD '{$password}';\"";

        // Grant privileges to each schema
        $grantPrivileges = $this->getPostgresGrantPrivileges($privileges);
        foreach ($schemas as $schema) {
            $commands[] = "sudo -u postgres psql -c \"GRANT {$grantPrivileges} ON DATABASE {$schema} TO {$username};\"";
        }

        // Verify user was created
        $commands[] = "sudo -u postgres psql -c \"SELECT usename FROM pg_user WHERE usename='{$username}';\"";

        return $commands;
    }

    protected function getGrantPrivileges(string $privileges): string
    {
        return match ($privileges) {
            'all' => 'ALL PRIVILEGES',
            'read_only' => 'SELECT',
            'read_write' => 'SELECT, INSERT, UPDATE, DELETE',
            default => throw new \InvalidArgumentException("Invalid privilege type: {$privileges}"),
        };
    }

    protected function getPostgresGrantPrivileges(string $privileges): string
    {
        return match ($privileges) {
            'all' => 'ALL PRIVILEGES',
            'read_only' => 'CONNECT',
            'read_write' => 'ALL PRIVILEGES',
            default => throw new \InvalidArgumentException("Invalid privilege type: {$privileges}"),
        };
    }
}
