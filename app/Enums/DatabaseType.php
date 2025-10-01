<?php

namespace App\Enums;

enum DatabaseType: string
{
    case MySQL = 'mysql';
    case MariaDB = 'mariadb';
    case PostgreSQL = 'postgresql';
    case MongoDB = 'mongodb';
    case Redis = 'redis';
}
