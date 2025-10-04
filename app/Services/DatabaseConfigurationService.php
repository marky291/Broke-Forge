<?php

namespace App\Services;

use App\Enums\DatabaseType;

class DatabaseConfigurationService
{
    public function getAvailableTypes(): array
    {
        return [
            DatabaseType::MySQL->value => [
                'name' => 'MySQL',
                'description' => 'Widely adopted relational database trusted for PHP applications.',
                'versions' => [
                    '8.0' => 'MySQL 8.0 (Recommended)',
                ],
                'default_version' => '8.0',
                'default_port' => 3306,
            ],
            DatabaseType::MariaDB->value => [
                'name' => 'MariaDB',
                'description' => 'High-performance MySQL-compatible database',
                'versions' => [
                    '11.4' => 'MariaDB 11.4 LTS (Recommended)',
                    '10.11' => 'MariaDB 10.11 LTS',
                    '10.6' => 'MariaDB 10.6 LTS',
                ],
                'default_version' => '11.4',
                'default_port' => 3306,
            ],
            DatabaseType::PostgreSQL->value => [
                'name' => 'PostgreSQL',
                'description' => 'Advanced open-source relational database with strong SQL compliance.',
                'versions' => [
                    '16' => 'PostgreSQL 16 (Recommended)',
                    '15' => 'PostgreSQL 15',
                ],
                'default_version' => '16',
                'default_port' => 5432,
            ],
        ];
    }

    public function getTypeConfiguration(DatabaseType $type): array
    {
        $configs = $this->getAvailableTypes();

        return $configs[$type->value] ?? [];
    }

    public function getDefaultPort(DatabaseType $type): int
    {
        return $this->getTypeConfiguration($type)['default_port'] ?? 3306;
    }

    public function getDefaultVersion(DatabaseType $type): string
    {
        return $this->getTypeConfiguration($type)['default_version'] ?? '8.0';
    }
}
