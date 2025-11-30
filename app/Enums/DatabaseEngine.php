<?php

namespace App\Enums;

enum DatabaseEngine: string
{
    case MySQL = 'mysql';
    case MariaDB = 'mariadb';
    case PostgreSQL = 'postgresql';
    case MongoDB = 'mongodb';
    case Redis = 'redis';

    public function storageType(): StorageType
    {
        return match ($this) {
            self::Redis => StorageType::Memory,
            default => StorageType::Disk,
        };
    }
}
