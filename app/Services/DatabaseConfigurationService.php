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
                'description' => 'Popular open-source relational database',
                'versions' => [
                    '8.0' => 'MySQL 8.0 (Recommended)',
                    '5.7' => 'MySQL 5.7 (Legacy)',
                ],
                'default_version' => '8.0',
                'default_port' => 3306,
            ],
            DatabaseType::PostgreSQL->value => [
                'name' => 'PostgreSQL',
                'description' => 'Advanced open-source relational database',
                'versions' => [
                    '16' => 'PostgreSQL 16 (Latest)',
                    '15' => 'PostgreSQL 15',
                    '14' => 'PostgreSQL 14',
                ],
                'default_version' => '16',
                'default_port' => 5432,
            ],
            DatabaseType::Redis->value => [
                'name' => 'Redis',
                'description' => 'In-memory data structure store',
                'versions' => [
                    '7.2' => 'Redis 7.2 (Latest)',
                    '7.0' => 'Redis 7.0',
                ],
                'default_version' => '7.2',
                'default_port' => 6379,
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
