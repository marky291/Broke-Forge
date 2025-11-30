<?php

namespace App\Packages\Services\Database;

use App\Enums\DatabaseEngine;
use App\Models\Server;
use App\Models\ServerDatabase;

/**
 * Fetches database users from MySQL/MariaDB/PostgreSQL servers
 */
class DatabaseUserFetcher
{
    /**
     * Fetch database users from the remote server
     *
     * @return array{users: array, error: string|null}
     */
    public function fetch(Server $server, ServerDatabase $database): array
    {
        $databaseEngine = $database->engine instanceof DatabaseEngine
            ? $database->engine
            : DatabaseEngine::from($database->engine);

        try {
            return match ($databaseEngine) {
                DatabaseEngine::MySQL, DatabaseEngine::MariaDB => $this->fetchMySqlUsers($server, $database),
                DatabaseEngine::PostgreSQL => $this->fetchPostgreSqlUsers($server, $database),
                default => ['users' => [], 'error' => 'Database engine does not support user management'],
            };
        } catch (\Exception $e) {
            return [
                'users' => [],
                'error' => 'Failed to fetch database users: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Fetch MySQL/MariaDB users
     *
     * @return array{users: array, error: string|null}
     */
    private function fetchMySqlUsers(Server $server, ServerDatabase $database): array
    {
        $ssh = $server->ssh('root');
        $password = $database->root_password;

        // Query to get all users with their host and basic privileges
        $query = "SELECT User, Host, Super_priv, Grant_priv FROM mysql.user WHERE User != '' ORDER BY User, Host";

        $result = $ssh->execute("mysql -u root -p{$password} -e \"{$query}\" -N -B");

        if (! $result->isSuccessful()) {
            return [
                'users' => [],
                'error' => 'Failed to connect to database: '.$result->getErrorOutput(),
            ];
        }

        $output = trim($result->getOutput());

        if (empty($output)) {
            return ['users' => [], 'error' => null];
        }

        $users = [];
        foreach (explode("\n", $output) as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 4) {
                $users[] = [
                    'user' => $parts[0],
                    'host' => $parts[1],
                    'has_super' => $parts[2] === 'Y',
                    'has_grant' => $parts[3] === 'Y',
                ];
            }
        }

        return ['users' => $users, 'error' => null];
    }

    /**
     * Fetch PostgreSQL users
     *
     * @return array{users: array, error: string|null}
     */
    private function fetchPostgreSqlUsers(Server $server, ServerDatabase $database): array
    {
        $ssh = $server->ssh('root');

        // Query to get all users with their roles
        $query = 'SELECT rolname, rolsuper, rolcreaterole FROM pg_roles WHERE rolcanlogin = true ORDER BY rolname';

        $result = $ssh->execute("sudo -u postgres psql -t -A -F'|' -c \"{$query}\"");

        if (! $result->isSuccessful()) {
            return [
                'users' => [],
                'error' => 'Failed to connect to database: '.$result->getErrorOutput(),
            ];
        }

        $output = trim($result->getOutput());

        if (empty($output)) {
            return ['users' => [], 'error' => null];
        }

        $users = [];
        foreach (explode("\n", $output) as $line) {
            $parts = explode('|', trim($line));
            if (count($parts) >= 3) {
                $users[] = [
                    'user' => $parts[0],
                    'host' => 'localhost', // PostgreSQL doesn't use host in the same way
                    'has_super' => $parts[1] === 't',
                    'has_grant' => $parts[2] === 't',
                ];
            }
        }

        return ['users' => $users, 'error' => null];
    }
}
