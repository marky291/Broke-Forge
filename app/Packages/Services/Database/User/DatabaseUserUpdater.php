<?php

namespace App\Packages\Services\Database\User;

use App\Models\Server;
use App\Models\ServerDatabase;
use App\Packages\Core\Base\PackageInstaller;

/**
 * Database User Updater
 *
 * Executes ALTER USER and GRANT/REVOKE commands on MySQL/MariaDB/PostgreSQL servers
 */
class DatabaseUserUpdater extends PackageInstaller implements \App\Packages\Core\Base\ServerPackage
{
    public function __construct(
        protected Server $server,
        protected ServerDatabase $database
    ) {}

    /**
     * Execute the database user update
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

        // Update password
        $commands[] = "mysql -u root -p{$rootPassword} -e \"ALTER USER '{$username}'@'{$host}' IDENTIFIED BY '{$password}';\"";

        // Revoke all privileges first
        $commands[] = "mysql -u root -p{$rootPassword} -e \"REVOKE ALL PRIVILEGES, GRANT OPTION FROM '{$username}'@'{$host}';\"";

        // Grant new privileges
        $grantPrivileges = $this->getGrantPrivileges($privileges);
        foreach ($schemas as $schema) {
            $commands[] = "mysql -u root -p{$rootPassword} -e \"GRANT {$grantPrivileges} ON \`{$schema}\`.* TO '{$username}'@'{$host}';\"";
        }

        // Flush privileges
        $commands[] = "mysql -u root -p{$rootPassword} -e \"FLUSH PRIVILEGES;\"";

        return $commands;
    }

    protected function postgresCommands(string $username, string $password, string $privileges, array $schemas): array
    {
        $commands = [];

        // Update password
        $commands[] = "sudo -u postgres psql -c \"ALTER USER {$username} WITH PASSWORD '{$password}';\"";

        // Revoke existing privileges
        foreach ($schemas as $schema) {
            $commands[] = "sudo -u postgres psql -c \"REVOKE ALL PRIVILEGES ON DATABASE {$schema} FROM {$username};\"";
        }

        // Grant new privileges
        $grantPrivileges = $this->getPostgresGrantPrivileges($privileges);
        foreach ($schemas as $schema) {
            $commands[] = "sudo -u postgres psql -c \"GRANT {$grantPrivileges} ON DATABASE {$schema} TO {$username};\"";
        }

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
